<?php
namespace Deployer;

require 'recipe/laravel.php';

set('repository', 'https://github.com/Korbe/meSync.git');

add('shared_files', ['.env']);
add('shared_dirs', ['storage']);
add('writable_dirs', [
    'storage',
    'bootstrap/cache',
]);

host('217.154.67.144')
    ->set('ssh_multiplexing', false)
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/var/www/mesync');

// PHP-Abhängigkeiten installieren
task('app:composer-install', function () {
    cd('{{release_path}}');
    run('composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader');
});

// Frontend-Abhängigkeiten installieren
task('app:npm-install', function () {
    cd('{{release_path}}');
    run('npm ci');
});

// Vite-Assets erzeugen
task('app:npm-build', function () {
    cd('{{release_path}}');
    run('npm run build');
});

after('deploy:update_code', 'app:composer-install');
after('app:composer-install', 'app:npm-install');
after('app:npm-install', 'app:npm-build');

after('deploy:failed', 'deploy:unlock');