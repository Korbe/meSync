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
    ->set('deploy_path', '/var/www/html/mesync');

task('build', function () {
    cd('{{release_path}}');
    run('composer install');
    run('npm install');
    run('npm run build');
    });

after('deploy:update_code', 'build');

//after('deploy:setup', 'deploy:unlock');

after('deploy:failed', 'deploy:unlock');
