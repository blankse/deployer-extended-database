<?php

namespace Deployer;

task('db:copy', function () {
    if (null === input()->getArgument('stage')) {
        throw new \RuntimeException("The source instance is required for db:move command.");
    }
    if (input()->getArgument('targetStage')) {
        $targetInstanceName = input()->getArgument('targetStage');
        $targetInstanceEnv = Deployer::get()->environments[$targetInstanceName];
        if ($targetInstanceName == null) {
            throw new \RuntimeException(
                "You must set the target instance the database will be copied to as second parameter."
            );
        }
        // TODO - instance name hardcoded
        if ($targetInstanceName == 'live') {
            throw new \RuntimeException(
                "FORBIDDEN: For security its forbidden to move database to live instance!"
            );
        }
        // TODO - instance name hardcoded
        if ($targetInstanceName == 'local') {
            throw new \RuntimeException(
                "FORBIDDEN: For synchro local database use: \ndep db:pull live"
            );
        }
    } else {
        throw new \RuntimeException(
            "The target instance is not set as second parameter. Move should be run as: dep db:move source target"
        );
    }
    //(Deployer::get()->servers[$targetInstanceName])->run('cat {{deploy_path}}/current/.env');
    $sourceInstance = get('server')['name'];
    $dumpCode = md5(microtime(true) . rand(0, 10000));
    if ($sourceInstance == 'local') {
        runLocally("cd {{deploy_path}} && {{local/bin/php}} {{local/bin/deployer}} -q db:export --dumpcode=$dumpCode");
    } else {
        run("cd {{deploy_path}}/current && {{bin/php}} {{bin/deployer}} -q db:export --dumpcode=$dumpCode");
        runLocally("{{local/bin/deployer}} db:download $sourceInstance --dumpcode=$dumpCode", 0);
    }
    runLocally("{{local/bin/deployer}} db:process --dumpcode=$dumpCode", 0);
    if (get('db_instance') == $targetInstanceName) {
        runLocally("{{local/bin/deployer}} db:import --dumpcode=$dumpCode", 0);
    } else {
        runLocally("{{local/bin/deployer}} db:upload $targetInstanceName --dumpcode=$dumpCode", 0);
        run("cd " . $targetInstanceEnv->get('deploy_path') . "/current && " . $targetInstanceEnv->get('bin/php') .
            $targetInstanceEnv->get('bin/deployer') . " -q db:import --dumpcode=" . $dumpCode);

    }
})->desc('Synchronize database between instances.');
