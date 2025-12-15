[{{ $account->username }}]
; Pool configuration for {{ $account->username }}
; Domain: {{ $account->domain }}

user = {{ $account->username }}
group = {{ $account->username }}

listen = /var/run/php/php{{ $phpVersion }}-fpm-{{ $account->username }}.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process Manager
pm = dynamic
pm.max_children = {{ $maxChildren ?? 5 }}
pm.start_servers = {{ $startServers ?? 2 }}
pm.min_spare_servers = {{ $minSpare ?? 1 }}
pm.max_spare_servers = {{ $maxSpare ?? 3 }}
pm.max_requests = {{ $maxRequests ?? 500 }}

; Timeouts
request_terminate_timeout = {{ $timeout ?? 300 }}

; Logging
php_admin_value[error_log] = /home/{{ $account->username }}/logs/php-error.log
php_admin_flag[log_errors] = on

; Security
php_admin_value[open_basedir] = /home/{{ $account->username }}:/tmp:/usr/share/php
php_admin_value[upload_tmp_dir] = /home/{{ $account->username }}/tmp
php_admin_value[session.save_path] = /home/{{ $account->username }}/tmp
php_admin_value[sys_temp_dir] = /home/{{ $account->username }}/tmp

; Disable dangerous functions
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Resource Limits
php_admin_value[memory_limit] = {{ $memoryLimit ?? '256M' }}
php_admin_value[post_max_size] = {{ $postMaxSize ?? '64M' }}
php_admin_value[upload_max_filesize] = {{ $uploadMaxSize ?? '64M' }}
php_admin_value[max_execution_time] = {{ $maxExecutionTime ?? 300 }}
php_admin_value[max_input_time] = {{ $maxInputTime ?? 300 }}

; Chroot (if enabled)
@if($chroot ?? false)
chroot = /home/{{ $account->username }}
chdir = /public_html
@endif
