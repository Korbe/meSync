<?php
namespace Deployer;

require 'recipe/laravel.php';

// Config

set('repository', 'https://github.com/Korbe/meSync.git');

// The Laravel recipe already shares .env and storage/ (incl. the private receipts and
// invoice originals) between releases, makes storage/ and bootstrap/cache writable,
// runs composer install, storage:link, the caches and the migrations.

// PHP-FPM service on the server (systemctl name)
set('php_fpm_service', 'php8.3-fpm');

// composer install on a small server can take a while
set('default_timeout', 900);

host('217.154.67.144')
    ->set('ssh_multiplexing', false)
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/var/www/mesync');

// The server deploys this branch from GitHub
set('branch', 'master');

// Tasks

// The assets are built from the local working copy, the code comes from GitHub: both must
// be the same commit. Stops before anything happens on the server if they aren't.
// Emergency override: dep deploy -o skip_git_check=true
task('deploy:check_local', function () {
    if (get('skip_git_check', false)) {
        warning('Git-Prüfung übersprungen (skip_git_check).');

        return;
    }

    $branch = get('branch');

    $changes = runLocally('git status --porcelain');
    if ($changes !== '') {
        throw error("Lokal gibt es nicht committete Änderungen – erst committen (oder verwerfen), dann deployen:\n{$changes}");
    }

    $current = runLocally('git rev-parse --abbrev-ref HEAD');
    if ($current !== $branch) {
        throw error("Du bist auf Branch \"{$current}\" – deployt wird \"{$branch}\". Erst wechseln: git checkout {$branch}");
    }

    runLocally("git fetch origin {$branch} --quiet");
    $local = runLocally('git rev-parse HEAD');
    $remote = runLocally("git rev-parse origin/{$branch}");

    if ($local !== $remote) {
        $ahead = (int) runLocally("git rev-list --count origin/{$branch}..HEAD");
        $behind = (int) runLocally("git rev-list --count HEAD..origin/{$branch}");

        throw error(match (true) {
            $ahead > 0 && $behind > 0 => "Lokal und GitHub sind auseinandergelaufen ({$ahead} Commits nur lokal, {$behind} nur auf GitHub) – erst git pull, dann git push.",
            $ahead > 0 => "{$ahead} Commit(s) noch nicht auf GitHub – erst git push, dann deployen.",
            default => "GitHub hat {$behind} neuere Commit(s) – erst git pull, dann deployen.",
        });
    }

    info('Lokaler Stand = GitHub ('.substr($local, 0, 7).' auf '.$branch.')');
});

// Build the assets locally instead of on the server and upload the result:
// no Node.js needed on the server, no npm running there.
task('assets:build', function () {
    runLocally('npm ci');
    runLocally('npm run build');
});

task('assets:upload', function () {
    // upload() needs rsync, which Windows doesn't ship with; scp uses the same connection as dep
    $target = currentHost()->connectionString();
    run('mkdir -p {{release_path}}/public/build');
    runLocally("scp -r public/build/. {$target}:{{release_path}}/public/build/");
});

// Reload instead of restart: PHP-FPM empties its OPcache (the new release behind the
// "current" symlink is picked up) without dropping running requests
task('fpm:reload', function () {
    run('sudo systemctl reload {{php_fpm_service}}');
});

// Hooks

before('deploy', 'deploy:check_local');
after('deploy:vendors', 'assets:build');
after('assets:build', 'assets:upload');

// The queue worker (systemd: mesync-queue) ends after its current job and is started
// again by systemd in the new release; nginx doesn't need a reload (config unchanged)
after('deploy:success', 'fpm:reload');
after('deploy:success', 'artisan:queue:restart');

after('deploy:setup', 'deploy:unlock');
after('deploy:failed', 'deploy:unlock');
