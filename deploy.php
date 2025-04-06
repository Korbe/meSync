<?php
namespace Deployer;

require 'recipe/laravel.php';

// Config

set('repository', 'https://github.com/Korbe/meSync.git');

add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts

host('192.168.0.135')
    ->set('ssh_multiplexing', false)
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '/var/www/deployment/mesync');

task('composer-install', function () {
    cd('{{release_path}}');
    run('composer install');
    });

task('npm-install', function () {
    cd('{{release_path}}');
    run('npm install');
});

task('build', function () {
    cd('{{release_path}}');
    run('npm run build');
    });

// task('permissions', function () {
//     run('chown -R deploy:www-data {{release_path}}');
//     run('chmod -R 775 {{release_path}}');
// });

//after('deploy:symlink', 'permissions');

after('deploy:update_code', 'composer-install');
// after('composer-install', 'npm-install');
after('npm-install', 'build');

//after('deploy:setup', 'deploy:unlock');

after('deploy:failed', 'deploy:unlock');
