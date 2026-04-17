<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\GitRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class GitController extends Controller
{
    /**
     * Regex guards applied before interpolating any value into a shell
     * command or filesystem path. The repository, account, and branch
     * strings come from validated request data or DB rows originally
     * seeded from validated input, but we re-check at every shell
     * boundary as defense-in-depth.
     */
    protected function assertValidUsername(string $username): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $username)) {
            abort(422, 'Invalid system username');
        }
    }

    protected function assertValidRepoName(string $name): void
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $name)) {
            abort(422, 'Invalid repository name');
        }
    }

    protected function assertValidBranch(string $branch): void
    {
        // Git ref names allow a lot of characters; stay conservative.
        if (! preg_match('/^[A-Za-z0-9._\/-]{1,100}$/', $branch) || str_contains($branch, '..')) {
            abort(422, 'Invalid branch name');
        }
    }

    protected function assertValidRef(string $ref): void
    {
        if (! preg_match('/^[A-Za-z0-9._\/-]{1,100}$/', $ref) || str_contains($ref, '..')) {
            abort(422, 'Invalid git ref');
        }
    }

    protected function assertValidRelativePath(string $path): void
    {
        // Must not start with `/` or `-` (avoid arg injection) and must
        // not contain `..` segments. Empty path (repo root) is fine.
        if ($path === '') {
            return;
        }
        if (str_starts_with($path, '/') || str_starts_with($path, '-')) {
            abort(422, 'Invalid path');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' || ! preg_match('/^[A-Za-z0-9._-]*$/', $segment)) {
                abort(422, 'Invalid path');
            }
        }
    }

    /**
     * Restrict clone URLs to public https:// / http:// / ssh:// / git@ forms.
     * `file://` reads from the local filesystem; ssh to localhost or
     * cloud-metadata addresses would let a user exfiltrate host data.
     */
    protected function assertSafeCloneUrl(string $url): void
    {
        if (preg_match('/^(git|ssh):\/\//i', $url) || preg_match('/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+:/', $url)) {
            // ssh-style URLs — ok.
        } elseif (preg_match('/^https?:\/\//i', $url)) {
            // plain HTTP(S) — ok after host check.
        } else {
            abort(422, 'Only http(s), ssh, or git@ clone URLs are allowed');
        }

        // Block shell metacharacters entirely.
        if (preg_match('/[\s;&|`$(){}\\\\<>\'"]/', $url) || str_contains($url, "\n") || str_contains($url, "\r")) {
            abort(422, 'Clone URL contains disallowed characters');
        }

        // Block hostnames that resolve to private/link-local/metadata
        // addresses. Only meaningful for http(s) URLs.
        if (preg_match('#^https?://([^/:]+)#i', $url, $m)) {
            $host = strtolower($m[1]);
            $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '169.254.169.254', 'metadata.google.internal'];
            if (in_array($host, $blockedHosts, true)) {
                abort(422, 'Clone URL host is not permitted');
            }
            foreach (gethostbynamel($host) ?: [$host] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    abort(422, 'Clone URL resolves to a private/reserved address');
                }
            }
        }
    }

    /**
     * Absolute path to the account's repositories directory.
     */
    protected function repositoriesDir(string $username): string
    {
        return "/home/{$username}/repositories";
    }

    /**
     * Absolute path for a given repo name. Rebuilt here (not taken from
     * the model) so a mutated `GitRepository::$path` can't escape.
     */
    protected function repoPath(string $username, string $name): string
    {
        return $this->repositoriesDir($username).'/'.$name.'.git';
    }

    /**
     * List Git repositories for the authenticated user
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;

        $repositories = GitRepository::where('account_id', $account->id)
            ->orderBy('name')
            ->get();

        return $this->success($repositories);
    }

    /**
     * Create a new Git repository
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;
        $this->assertValidUsername($account->username);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            'branch' => 'string|max:100|regex:/^[A-Za-z0-9._\/-]+$/',
            'deploy_path' => 'nullable|string|max:255',
            'auto_deploy' => 'boolean',
            'deploy_script' => 'nullable|string',
            'is_private' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if repository already exists
        if (GitRepository::where('account_id', $account->id)->where('name', $request->name)->exists()) {
            return $this->error('Repository with this name already exists', 422);
        }

        $branch = $request->input('branch', 'main');
        $this->assertValidBranch($branch);

        $fullPath = $this->repoPath($account->username, $request->name);

        Process::run(['sudo', 'mkdir', '-p', $this->repositoriesDir($account->username)]);
        Process::run(['sudo', 'git', 'init', '--bare', $fullPath]);
        Process::run(['sudo', 'chown', '-R', "{$account->username}:{$account->username}", $fullPath]);
        Process::run(['sudo', 'git', '-C', $fullPath, 'symbolic-ref', 'HEAD', "refs/heads/{$branch}"]);

        $repository = GitRepository::create([
            'account_id' => $account->id,
            'name' => $request->name,
            'path' => "{$request->name}.git",
            'branch' => $branch,
            'deploy_path' => $request->deploy_path,
            'auto_deploy' => $request->boolean('auto_deploy'),
            'deploy_script' => $request->deploy_script,
            'is_private' => $request->boolean('is_private', true),
        ]);

        if ($repository->auto_deploy) {
            $this->setupDeployHook($repository);
        }

        $repository->clone_url = $repository->ssh_url;
        $repository->save();

        return $this->success([
            'repository' => $repository,
            'ssh_url' => $repository->ssh_url,
            'https_url' => $repository->https_url,
        ], 'Repository created');
    }

    /**
     * Show a specific repository
     */
    public function show(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($gitRepository->name);
        $fullPath = $this->repoPath($account->username, $gitRepository->name);

        $result = Process::run(['git', '-C', $fullPath, 'log', '--oneline', '-10']);
        $commits = [];

        foreach (explode("\n", trim($result->output())) as $line) {
            if (empty($line)) {
                continue;
            }
            $parts = explode(' ', $line, 2);
            $commits[] = [
                'hash' => $parts[0],
                'message' => $parts[1] ?? '',
            ];
        }

        $result = Process::run(['git', '-C', $fullPath, 'branch']);
        $branches = array_filter(array_map('trim', explode("\n", $result->output())));

        $result = Process::run(['du', '-sh', $fullPath]);
        preg_match('/^([\d.]+[KMGT]?)/', $result->output(), $matches);
        $size = $matches[1] ?? '0K';

        return $this->success([
            'repository' => $gitRepository,
            'ssh_url' => $gitRepository->ssh_url,
            'https_url' => $gitRepository->https_url,
            'commits' => $commits,
            'branches' => $branches,
            'size' => $size,
        ]);
    }

    /**
     * Update a repository
     */
    public function update(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'branch' => 'string|max:100|regex:/^[A-Za-z0-9._\/-]+$/',
            'deploy_path' => 'nullable|string|max:255',
            'auto_deploy' => 'boolean',
            'deploy_script' => 'nullable|string',
            'is_private' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $gitRepository->update($request->only([
            'branch',
            'deploy_path',
            'auto_deploy',
            'deploy_script',
            'is_private',
        ]));

        if ($gitRepository->auto_deploy) {
            $this->setupDeployHook($gitRepository);
        } else {
            $this->removeDeployHook($gitRepository);
        }

        return $this->success($gitRepository, 'Repository updated');
    }

    /**
     * Delete a repository
     */
    public function destroy(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($gitRepository->name);
        $fullPath = $this->repoPath($account->username, $gitRepository->name);

        Process::run(['sudo', 'rm', '-rf', '--', $fullPath]);

        $gitRepository->delete();

        return $this->success(null, 'Repository deleted');
    }

    /**
     * Clone a repository from external URL
     */
    public function cloneRepo(Request $request)
    {
        $account = $request->user()->account;
        $this->assertValidUsername($account->username);

        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:500',
            'name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            'branch' => 'string|max:100|regex:/^[A-Za-z0-9._\/-]+$/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $url = (string) $request->input('url');
        $this->assertSafeCloneUrl($url);

        if (GitRepository::where('account_id', $account->id)->where('name', $request->name)->exists()) {
            return $this->error('Repository with this name already exists', 422);
        }

        $branch = $request->input('branch', 'main');
        $this->assertValidBranch($branch);

        $fullPath = $this->repoPath($account->username, $request->name);

        Process::run(['sudo', 'mkdir', '-p', $this->repositoriesDir($account->username)]);
        $result = Process::run(['sudo', 'git', 'clone', '--bare', '--', $url, $fullPath]);

        if (! $result->successful()) {
            return $this->error('Failed to clone repository: '.$result->errorOutput(), 500);
        }

        Process::run(['sudo', 'chown', '-R', "{$account->username}:{$account->username}", $fullPath]);

        $repository = GitRepository::create([
            'account_id' => $account->id,
            'name' => $request->name,
            'path' => "{$request->name}.git",
            'branch' => $branch,
            'clone_url' => $url,
            'is_private' => true,
        ]);

        return $this->success([
            'repository' => $repository,
            'ssh_url' => $repository->ssh_url,
            'https_url' => $repository->https_url,
        ], 'Repository cloned');
    }

    /**
     * Pull latest changes from remote
     */
    public function pull(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        if (! $gitRepository->clone_url) {
            return $this->error('Repository has no remote URL', 400);
        }

        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($gitRepository->name);
        $fullPath = $this->repoPath($account->username, $gitRepository->name);

        $result = Process::run(['sudo', '-u', $account->username, 'git', '-C', $fullPath, 'fetch', 'origin']);

        if (! $result->successful()) {
            return $this->error('Pull failed: '.$result->errorOutput(), 500);
        }

        return $this->success(null, 'Repository updated from remote');
    }

    /**
     * Trigger manual deploy
     */
    public function deploy(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        if (! $gitRepository->deploy_path) {
            return $this->error('No deploy path configured', 400);
        }

        $output = $this->runDeploy($gitRepository);

        return $this->success(['output' => $output], 'Deployment completed');
    }

    /**
     * Get deployment logs
     */
    public function deployLogs(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($gitRepository->name);
        $logPath = "/home/{$account->username}/logs/deploy-{$gitRepository->name}.log";

        $result = Process::run(['tail', '-n', '100', $logPath]);

        return $this->success([
            'logs' => $result->output() ?: 'No deployment logs found',
        ]);
    }

    /**
     * Get repository file tree
     */
    public function files(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($gitRepository->name);
        $fullPath = $this->repoPath($account->username, $gitRepository->name);

        $path = (string) $request->input('path', '');
        $ref = (string) $request->input('ref', 'HEAD');

        $this->assertValidRelativePath($path);
        $this->assertValidRef($ref);

        $args = ['git', '-C', $fullPath, 'ls-tree', $ref];
        if ($path !== '') {
            $args[] = $path;
        }
        $result = Process::run($args);

        $files = [];
        foreach (explode("\n", trim($result->output())) as $line) {
            if (empty($line)) {
                continue;
            }

            preg_match('/^(\d+)\s+(\w+)\s+(\w+)\s+(.+)$/', $line, $matches);

            if ($matches) {
                $files[] = [
                    'mode' => $matches[1],
                    'type' => $matches[2], // blob or tree
                    'hash' => $matches[3],
                    'name' => $matches[4],
                ];
            }
        }

        return $this->success(['files' => $files]);
    }

    /**
     * Get file content
     */
    public function fileContent(Request $request, GitRepository $gitRepository)
    {
        $account = $request->user()->account;

        if ($gitRepository->account_id !== $account->id) {
            return $this->error('Repository not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'path' => 'required|string|max:500',
            'ref' => 'string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($gitRepository->name);
        $fullPath = $this->repoPath($account->username, $gitRepository->name);

        $ref = (string) $request->input('ref', 'HEAD');
        $filePath = (string) $request->input('path');

        $this->assertValidRef($ref);
        $this->assertValidRelativePath($filePath);

        $result = Process::run(['git', '-C', $fullPath, 'show', "{$ref}:{$filePath}"]);

        if (! $result->successful()) {
            return $this->error('File not found', 404);
        }

        return $this->success([
            'content' => $result->output(),
            'path' => $filePath,
        ]);
    }

    /**
     * Set up post-receive deploy hook
     */
    protected function setupDeployHook(GitRepository $repository): void
    {
        $account = $repository->account;
        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($repository->name);
        $this->assertValidBranch((string) $repository->branch);

        $fullRepoPath = $this->repoPath($account->username, $repository->name);
        $hookPath = "{$fullRepoPath}/hooks/post-receive";

        $deployScript = $repository->deploy_script ?: GitRepository::getDefaultDeployScript($this->detectProjectType($repository));
        $deployPath = $this->sanitizeDeployPath($account->username, (string) $repository->deploy_path);
        $logPath = "/home/{$account->username}/logs/deploy-{$repository->name}.log";

        // Use escapeshellarg in the template values we interpolate into the
        // bash source that gets written to disk. The customer's own
        // $deployScript is deliberately executed as shell — that's the
        // feature — but surrounding metadata stays inert.
        $deployPathSh = escapeshellarg($deployPath);
        $logPathSh = escapeshellarg($logPath);
        $branchSh = escapeshellarg((string) $repository->branch);
        $indentedScript = $this->indentScript((string) $deployScript, 8);

        $hook = <<<HOOK
#!/bin/bash
# FreePanel auto-deploy hook
DEPLOY_PATH={$deployPathSh}
LOG_FILE={$logPathSh}
BRANCH={$branchSh}

while read oldrev newrev refname; do
    branch=\$(git rev-parse --symbolic --abbrev-ref \$refname)

    if [ "\$branch" = "\$BRANCH" ]; then
        echo "[\$(date)] Deploying \$branch to \$DEPLOY_PATH" >> "\$LOG_FILE"

        # Checkout files to deploy path
        GIT_WORK_TREE="\$DEPLOY_PATH" git checkout -f \$branch >> "\$LOG_FILE" 2>&1

        cd "\$DEPLOY_PATH" || exit 1

        # Run deploy script
{$indentedScript} >> "\$LOG_FILE" 2>&1

        echo "[\$(date)] Deployment complete" >> "\$LOG_FILE"
    fi
done
HOOK;

        $tempFile = tempnam(sys_get_temp_dir(), 'hook_');
        chmod($tempFile, 0600);
        file_put_contents($tempFile, $hook);

        Process::run(['sudo', 'mv', $tempFile, $hookPath]);
        Process::run(['sudo', 'chmod', '+x', $hookPath]);
        Process::run(['sudo', 'chown', "{$account->username}:{$account->username}", $hookPath]);
    }

    /**
     * Remove deploy hook
     */
    protected function removeDeployHook(GitRepository $repository): void
    {
        $account = $repository->account;
        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($repository->name);

        $hookPath = $this->repoPath($account->username, $repository->name).'/hooks/post-receive';
        Process::run(['sudo', 'rm', '-f', '--', $hookPath]);
    }

    /**
     * Run manual deployment
     */
    protected function runDeploy(GitRepository $repository): string
    {
        $account = $repository->account;
        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($repository->name);
        $this->assertValidBranch((string) $repository->branch);

        $deployPath = $this->sanitizeDeployPath($account->username, (string) $repository->deploy_path);
        $fullRepoPath = $this->repoPath($account->username, $repository->name);

        Process::run(['sudo', '-u', $account->username, 'mkdir', '-p', $deployPath]);
        Process::run([
            'sudo', '-u', $account->username,
            'git', '-C', $fullRepoPath,
            "--work-tree={$deployPath}",
            'checkout', '-f', $repository->branch,
        ]);

        $script = $repository->deploy_script ?: GitRepository::getDefaultDeployScript($this->detectProjectType($repository));
        $tempScript = tempnam(sys_get_temp_dir(), 'deploy_');
        chmod($tempScript, 0600);

        // The customer's deploy script is executed as shell by design;
        // only the `cd` target is templated in, via escapeshellarg.
        $deployPathSh = escapeshellarg($deployPath);
        file_put_contents($tempScript, "#!/bin/bash\ncd {$deployPathSh} || exit 1\n{$script}\n");
        chmod($tempScript, 0700);

        $result = Process::run(['sudo', '-u', $account->username, $tempScript]);
        @unlink($tempScript);

        return $result->output().$result->errorOutput();
    }

    /**
     * Constrain deploy paths to the account's home directory.
     */
    protected function sanitizeDeployPath(string $username, string $path): string
    {
        $homeDir = "/home/{$username}";
        if ($path === '' || str_contains($path, '..')) {
            abort(422, 'Invalid deploy path');
        }

        $absolute = str_starts_with($path, '/') ? $path : $homeDir.'/'.$path;

        if (! str_starts_with($absolute, $homeDir.'/') && $absolute !== $homeDir) {
            abort(422, 'Deploy path must be inside the account home directory');
        }

        return $absolute;
    }

    /**
     * Detect project type from repository
     */
    protected function detectProjectType(GitRepository $repository): string
    {
        $account = $repository->account;
        $this->assertValidUsername($account->username);
        $this->assertValidRepoName($repository->name);
        $fullPath = $this->repoPath($account->username, $repository->name);

        $result = Process::run(['git', '-C', $fullPath, 'ls-tree', '--name-only', 'HEAD']);
        $files = explode("\n", $result->output());

        if (in_array('package.json', $files)) {
            return 'nodejs';
        } elseif (in_array('requirements.txt', $files) || in_array('setup.py', $files)) {
            return 'python';
        } elseif (in_array('composer.json', $files)) {
            return 'php';
        } elseif (in_array('Gemfile', $files)) {
            return 'ruby';
        }

        return 'static';
    }

    /**
     * Indent script lines
     */
    protected function indentScript(string $script, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);

        return $indent.str_replace("\n", "\n{$indent}", trim($script));
    }
}
