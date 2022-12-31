<?php

/**
 * @codingStandardsIgnoreStart
 */

declare(strict_types=1);

use FFI\VarDumper\Caster\FFICDataCaster;
use FFI\VarDumper\Caster\FFICTypeCaster;
use FFI\CData;
use FFI\CType;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

// symfony/var-dumper IDEA/PhpStorm CLI bugfix
if (isset($_SERVER['IDEA_INITIAL_DIRECTORY'])) {
    putenv('TERMINAL_EMULATOR=JetBrains-JediTerm');
}

/** @psalm-suppress MixedArrayAssignment */
AbstractCloner::$defaultCasters[CType::class] = [FFICTypeCaster::class, 'castCType'];

/** @psalm-suppress MixedArrayAssignment */
AbstractCloner::$defaultCasters[CData::class] = [FFICDataCaster::class, 'castCData'];
