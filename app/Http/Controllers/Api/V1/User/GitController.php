<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\GitRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GitController extends Controller
{
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            'branch' => 'string|max:50',
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

        // Create repository directory
        $repoPath = "{$request->name}.git";
        $fullPath = "/home/{$account->username}/repositories/{$repoPath}";

        // Initialize bare git repository
        Process::run("sudo mkdir -p /home/{$account->username}/repositories");
        Process::run("sudo git init --bare {$fullPath}");
        Process::run("sudo chown -R {$account->username}:{$account->username} {$fullPath}");

        // Set default branch
        $branch = $request->input('branch', 'main');
        Process::run("sudo git -C {$fullPath} symbolic-ref HEAD refs/heads/{$branch}");

        $repository = GitRepository::create([
            'account_id' => $account->id,
            'name' => $request->name,
            'path' => $repoPath,
            'branch' => $branch,
            'deploy_path' => $request->deploy_path,
            'auto_deploy' => $request->boolean('auto_deploy'),
            'deploy_script' => $request->deploy_script,
            'is_private' => $request->boolean('is_private', true),
        ]);

        // Set up post-receive hook if auto-deploy is enabled
        if ($repository->auto_deploy) {
            $this->setupDeployHook($repository);
        }

        // Generate clone URLs
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

        // Get recent commits
        $fullPath = $gitRepository->full_path;
        $result = Process::run("git -C {$fullPath} log --oneline -10 2>/dev/null");
        $commits = [];

        foreach (explode("\n", trim($result->output())) as $line) {
            if (empty($line)) continue;
            $parts = explode(' ', $line, 2);
            $commits[] = [
                'hash' => $parts[0],
                'message' => $parts[1] ?? '',
            ];
        }

        // Get branches
        $result = Process::run("git -C {$fullPath} branch 2>/dev/null");
        $branches = array_filter(array_map('trim', explode("\n", $result->output())));

        // Get repository size
        $result = Process::run("du -sh {$fullPath}");
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
            'branch' => 'string|max:50',
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

        // Update deploy hook
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

        // Delete repository directory
        $fullPath = $gitRepository->full_path;
        Process::run("sudo rm -rf {$fullPath}");

        $gitRepository->delete();

        return $this->success(null, 'Repository deleted');
    }

    /**
     * Clone a repository from external URL
     */
    public function cloneRepo(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            'branch' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if repository already exists
        if (GitRepository::where('account_id', $account->id)->where('name', $request->name)->exists()) {
            return $this->error('Repository with this name already exists', 422);
        }

        $repoPath = "{$request->name}.git";
        $fullPath = "/home/{$account->username}/repositories/{$repoPath}";
        $branch = $request->input('branch', 'main');

        // Clone as bare repository
        Process::run("sudo mkdir -p /home/{$account->username}/repositories");
        $result = Process::run("sudo git clone --bare {$request->url} {$fullPath}");

        if (!$result->successful()) {
            return $this->error('Failed to clone repository: ' . $result->errorOutput(), 500);
        }

        Process::run("sudo chown -R {$account->username}:{$account->username} {$fullPath}");

        $repository = GitRepository::create([
            'account_id' => $account->id,
            'name' => $request->name,
            'path' => $repoPath,
            'branch' => $branch,
            'clone_url' => $request->url,
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

        if (!$gitRepository->clone_url) {
            return $this->error('Repository has no remote URL', 400);
        }

        $fullPath = $gitRepository->full_path;
        $result = Process::run("sudo -u {$account->username} git -C {$fullPath} fetch origin");

        if (!$result->successful()) {
            return $this->error('Pull failed: ' . $result->errorOutput(), 500);
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

        if (!$gitRepository->deploy_path) {
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

        $logPath = "/home/{$account->username}/logs/deploy-{$gitRepository->name}.log";
        $result = Process::run("tail -100 {$logPath} 2>/dev/null");

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

        $fullPath = $gitRepository->full_path;
        $path = $request->input('path', '');
        $ref = $request->input('ref', 'HEAD');

        $result = Process::run("git -C {$fullPath} ls-tree {$ref} {$path} 2>/dev/null");

        $files = [];
        foreach (explode("\n", trim($result->output())) as $line) {
            if (empty($line)) continue;

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
            'path' => 'required|string',
            'ref' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $fullPath = $gitRepository->full_path;
        $ref = $request->input('ref', 'HEAD');
        $filePath = $request->path;

        $result = Process::run("git -C {$fullPath} show {$ref}:{$filePath} 2>/dev/null");

        if (!$result->successful()) {
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
        $hookPath = "{$repository->full_path}/hooks/post-receive";

        $deployScript = $repository->deploy_script ?: GitRepository::getDefaultDeployScript($this->detectProjectType($repository));
        $deployPath = $repository->deploy_path;
        $logPath = "/home/{$account->username}/logs/deploy-{$repository->name}.log";

        $hook = <<<HOOK
#!/bin/bash
# FreePanel auto-deploy hook
DEPLOY_PATH="{$deployPath}"
LOG_FILE="{$logPath}"
BRANCH="{$repository->branch}"

while read oldrev newrev refname; do
    branch=\$(git rev-parse --symbolic --abbrev-ref \$refname)

    if [ "\$branch" = "\$BRANCH" ]; then
        echo "[\$(date)] Deploying \$branch to \$DEPLOY_PATH" >> \$LOG_FILE

        # Checkout files to deploy path
        GIT_WORK_TREE=\$DEPLOY_PATH git checkout -f \$branch >> \$LOG_FILE 2>&1

        cd \$DEPLOY_PATH

        # Run deploy script
{$this->indentScript($deployScript, 8)} >> \$LOG_FILE 2>&1

        echo "[\$(date)] Deployment complete" >> \$LOG_FILE
    fi
done
HOOK;

        $tempFile = tempnam('/tmp', 'hook_');
        file_put_contents($tempFile, $hook);

        Process::run("sudo mv {$tempFile} {$hookPath}");
        Process::run("sudo chmod +x {$hookPath}");
        Process::run("sudo chown {$account->username}:{$account->username} {$hookPath}");
    }

    /**
     * Remove deploy hook
     */
    protected function removeDeployHook(GitRepository $repository): void
    {
        $hookPath = "{$repository->full_path}/hooks/post-receive";
        Process::run("sudo rm -f {$hookPath}");
    }

    /**
     * Run manual deployment
     */
    protected function runDeploy(GitRepository $repository): string
    {
        $account = $repository->account;
        $deployPath = $repository->deploy_path;
        $fullRepoPath = $repository->full_path;

        // Checkout to deploy path
        Process::run("sudo -u {$account->username} mkdir -p {$deployPath}");
        Process::run("sudo -u {$account->username} git -C {$fullRepoPath} --work-tree={$deployPath} checkout -f {$repository->branch}");

        // Run deploy script
        $script = $repository->deploy_script ?: GitRepository::getDefaultDeployScript($this->detectProjectType($repository));
        $tempScript = tempnam('/tmp', 'deploy_');
        file_put_contents($tempScript, "#!/bin/bash\ncd {$deployPath}\n{$script}");
        chmod($tempScript, 0755);

        $result = Process::run("sudo -u {$account->username} {$tempScript}");
        unlink($tempScript);

        return $result->output() . $result->errorOutput();
    }

    /**
     * Detect project type from repository
     */
    protected function detectProjectType(GitRepository $repository): string
    {
        $fullPath = $repository->full_path;

        // Check for common files
        $result = Process::run("git -C {$fullPath} ls-tree --name-only HEAD 2>/dev/null");
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
        return $indent . str_replace("\n", "\n{$indent}", trim($script));
    }
}
