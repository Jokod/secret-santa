<?php

namespace Deployer;

require 'recipe/symfony.php';
require 'recipe/deploy/clear_paths.php';

set('sudo_cmd', 'sudo -n');
set('allow_anonymous_stats', false);
set('application', 'santa');
set('repository', 'git@github.com:Jokod/secret-santa.git');

set('shared_dirs', ['var/log']);
set('shared_files', ['.env.local']);
set('copy_dirs', ['vendor']);
set('clear_paths', ['tests', 'docker*', 'deploy*', 'README.md', '.gitlab-ci.yml', 'data']);

set('writable_dirs', ['var/log', 'var/cache']);
set('writable_mode', 'acl');
set('default_stage', 'production');

host('production')
    ->setHostname('51.38.191.167')
    ->set('labels', ['stage' => 'live'])
    ->set('port', 22)
    ->set('forwardAgent', true)
    ->set('multiplexing', true)
    ->setSshArguments([
        '-o UserKnownHostsFile=/dev/null',
        '-o StrictHostKeyChecking=no',
        '-o IdentitiesOnly=yes',
    ])
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/var/www/santa/production')
    ->set('identity_file', '~/.ssh/santa')
    ->set('branch', 'main')
    ->set('http_user', 'www-data')
    ->set('keep_releases', 10)
    ->set('verbose', true)
;

desc('Exécute make permissions en sudo');
task('make:permissions', function () {
    within('{{release_path}}', function () {
        run('{{sudo_cmd}} make permissions');
    });
});

desc('Nettoie le cache');
task('cache:clear', function () {
    within('{{release_path}}', function () {
        run('{{bin/console}} cache:clear');
    });
});

desc('Réchauffe le cache');
task('cache:warmup', function () {
    within('{{release_path}}', function () {
        run('{{bin/console}} cache:warmup');
    });
});

desc('Compile les assets avec AssetMapper');
task('assets:compile', function () {
    within('{{release_path}}', function () {
        run('{{bin/console}} assets:install && {{bin/console}} asset-map:compile');
    });
});

desc('Vérifie et crée le fichier .maintenance si nécessaire');
task('maintenance:check', function () {
    if ('staging' === get('labels')['stage']) {
        within('{{release_path}}', function () {
            run('touch .maintenance');
        });
    }
});

task('deploy:vendors', function () {
    $stage = get('labels')['stage'];
    within('{{release_path}}', function () use ($stage) {
        $options = get('composer_options', '');
        if ('staging' === $stage) {
            run('{{bin/composer}} install '.$options);
        } else {
            run('{{bin/composer}} install '.$options.' --no-dev');
        }
    });
});

after('deploy:failed', 'deploy:unlock');
after('deploy:update_code', 'deploy:copy_dirs');
after('deploy:vendors', 'deploy:clear_paths');
before('deploy:symlink', 'make:permissions');
after('deploy:cache:clear', 'cache:clear');
after('cache:clear', 'cache:warmup');
after('cache:warmup', 'assets:compile');
after('deploy:update_code', 'maintenance:check');
