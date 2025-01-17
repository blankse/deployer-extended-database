<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ArrayUtility;
use SourceBroker\DeployerExtendedDatabase\Utility\FileUtility;
use SourceBroker\DeployerExtendedDatabase\Utility\DatabaseUtility;
use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use Deployer\Exception\GracefulShutdownException;

/*
 * @see https://github.com/sourcebroker/deployer-extended-database#db-export
 */
task('db:export', function () {
    if (!empty((new ConsoleUtility())->getOption('dumpcode'))) {
        $returnDumpCode = false;
        $dumpCode = (new ConsoleUtility())->getOption('dumpcode');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dumpCode)) {
            throw new GracefulShutdownException('dumpcode can be only a-z, A-Z, 0-9', 1582316535496);
        }
    } else {
        $returnDumpCode = true;
        $dumpCode = md5(microtime(true) . random_int(0, 10000));
    }
    $fileUtility = new FileUtility();
    $arrayUtility = new ArrayUtility();
    $databaseUtility = new DatabaseUtility();
    if (get('is_argument_host_the_same_as_local_host')) {
        foreach (get('db_databases_merged') as $databaseCode => $databaseConfig) {
            $filenameParts = [
                'dateTime' => date('Y-m-d_H-i-s'),
                'server' => 'server=' . $fileUtility->normalizeFilename(get('local_host')),
                'dbcode' => 'dbcode=' . $fileUtility->normalizeFilename($databaseCode),
                'dumpcode' => 'dumpcode=' . $fileUtility->normalizeFilename($dumpCode),
                'type' => '',
            ];
            $mysqlDumpArgs = [
                'password' => escapeshellarg($databaseConfig['password']),
                'local/bin/mysqldump' => get('local/bin/mysqldump'),
                'options' => '',
                'host' => escapeshellarg($databaseConfig['host']),
                'port' => escapeshellarg((isset($databaseConfig['port']) && $databaseConfig['port']) ? $databaseConfig['port'] : 3306),
                'user' => escapeshellarg($databaseConfig['user']),
                'dbname' => escapeshellarg($databaseConfig['dbname']),
                'absolutePath' => '',
                'ignore-tables' => '',
            ];

            if (isset($databaseConfig['ignore_tables_out']) && is_array($databaseConfig['ignore_tables_out'])) {
                $ignoreTables = $arrayUtility->filterWithRegexp(
                    $databaseConfig['ignore_tables_out'],
                    $databaseUtility->getTables($databaseConfig)
                );
                if (!empty($ignoreTables)) {
                    $mysqlDumpArgs['ignore-tables'] = '--ignore-table=' . $databaseConfig['dbname'] . '.' .
                        implode(' --ignore-table=' . $databaseConfig['dbname'] . '.', $ignoreTables);
                }
            }
            // dump database structure
            $filenameParts['type'] = 'type=structure';
            $mysqlDumpArgs['options'] = get('db_export_mysqldump_options_structure', '');
            $mysqlDumpArgs['absolutePath'] = escapeshellarg($fileUtility->normalizeFolder(get('db_storage_path_local'))
                . implode('#', $filenameParts) . '.sql');
            runLocally(vsprintf(
                'export MYSQL_PWD=%s && %s %s -h%s -P%s -u%s %s -r%s'
                . ((new ConsoleUtility())->getOption('exportTaskAddIgnoreTablesToStructureDump') ? ' %s' : ''),
                $mysqlDumpArgs
            ));

            // dump database data
            $filenameParts['type'] = 'type=data';
            $mysqlDumpArgs['options'] = get('db_export_mysqldump_options_data', '');
            $mysqlDumpArgs['absolutePath'] = escapeshellarg($fileUtility->normalizeFolder(get('db_storage_path_local'))
                . implode('#', $filenameParts) . '.sql');
            runLocally(vsprintf(
                'export MYSQL_PWD=%s && %s %s -h%s -P%s -u%s %s -r%s %s',
                $mysqlDumpArgs
            ));
        }
    } else {
        $params = [
            get('argument_host'),
            (new ConsoleUtility())->getVerbosityAsParameter(),
            input()->getOption('options') ? '--options=' . input()->getOption('options') : '',
        ];
        run('cd {{release_or_current_path}} && {{bin/php}} {{bin/deployer}} db:export ' . implode(' ', $params));
    }
    if ($returnDumpCode) {
        writeln(json_encode(['dumpCode' => $dumpCode], JSON_THROW_ON_ERROR));
    }
})->desc('Dump database and store it in database dumps storage');
