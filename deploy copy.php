
<?php 

namespace Deployer;

require 'recipe/laravel.php';

// Repository
set('repository', 'https://github.com/Korbe/meSync.git');

// Deployment
host('217.154.67.144')
    ->set('ssh_multiplexing', false)
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/var/www/mesync');

// Laravel shared files and directories
set('shared_files', ['.env']);
set('shared_dirs', ['storage']);

set('writable_mode', 'chmod');
set('writable_dirs', [
    'storage',
    'bootstrap/cache',
]);

// Build frontend locally
task('deploy:build', function () {
    runLocally('npm run build');
})->desc('Build frontend assets locally');

// Upload built assets
task('deploy:upload_build', function () {
    $host = currentHost();
    $port = $host->getPort() ?? 22;
    $connection = $host->connectionString();

    runLocally(
        "scp -P {$port} -r public/build {$connection}:{{release_path}}/public/"
    );
})->desc('Upload frontend assets');

// Clear Laravel caches before rebuilding them
task('deploy:clear_caches', function () {
    run('{{bin/php}} {{release_path}}/artisan optimize:clear');
});

// Build Laravel caches
task('deploy:optimize', function () {
    run('{{bin/php}} {{release_path}}/artisan optimize');
});

// Hooks
before('deploy:update_code', 'deploy:build');

after('deploy:update_code', 'deploy:upload_build');
after('deploy:vendors', 'deploy:clear_caches');
after('deploy:clear_caches', 'deploy:optimize');

after('deploy:failed', 'deploy:unlock');
