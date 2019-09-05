<?php

namespace Facade\Ignition\SolutionProviders;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\QueryException;
use Symfony\Component\Console\Output\BufferedOutput;
use Facade\Ignition\Solutions\RunMigrationsSolution;
use Facade\IgnitionContracts\HasSolutionsForThrowable;

class MissingColumnSolutionProvider implements HasSolutionsForThrowable
{
    /**
     * See https://dev.mysql.com/doc/refman/8.0/en/server-error-reference.html#error_er_bad_field_error.
     */
    const MYSQL_BAD_FIELD_CODE = '42S22';

    public function canSolve(Throwable $throwable): bool
    {
        if (! $throwable instanceof QueryException) {
            return false;
        }
        $outputBuffer = new BufferedOutput();
        Artisan::call('migrate', ['--pretend' => true], $outputBuffer);
        if (! Str::contains($outputBuffer->fetch(), 'Nothing to migrate.')) {
            dump('No migrations');
        }

        preg_match('/Unknown column \'(.*?)\'.*?SQL: update `(.*?)`/', $throwable->getMessage(), $matches);
        [$fullMatch, $fieldName, $tableName] = $matches;

        foreach ($throwable->getTrace() as $frame) {
            if (
                isset($frame['class'])
                && $frame['class'] == 'Illuminate\Database\Query\Builder'
                && $frame['function'] == 'update'
            ) {
                $type = gettype($frame['args'][0][$fieldName]);
                if ($type === 'object') {
                    $type = get_class($frame['args'][0][$fieldName]);
                }
            }
        }

        switch ($type) {
            case 'string':
                break;
            case 'boolean':
                break;
            case 'integer':
                break;
            case 'Carbon\Carbon':
                break;
        }
        dump(compact('tableName', 'fieldName', 'type'));
        dd();

        return $this->isBadTableErrorCode($throwable->getCode());
    }

    protected function isBadTableErrorCode($code): bool
    {
        return $code === static::MYSQL_BAD_FIELD_CODE;
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [new RunMigrationsSolution('A column was not found')];
    }
}
