<?php

declare(strict_types=1);

namespace FFI\VarDumper\Caster;

use FFI\CData;
use FFI\CType;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\CutStub;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * @psalm-suppress all
 */
final class FFICDataCaster extends FFICaster
{
    /**
     * In case of "char*" contains a string, the length of which depends on
     * some other parameter, then during the generation of the string it is
     * possible to go beyond the allowable memory area.
     *
     * This restriction serves to ensure that processing does not take
     * up the entire allowable PHP memory limit.
     */
    private const MAX_STRING_LENGTH = 255;

    public static function castCData(CData $data, array $args, Stub $stub): array
    {
        $type = \FFI::typeof($data);

        $stub->class = $type->getName();
        $stub->handle = 0;

        return match (true) {
            self::isScalar($type), self::isEnum($type) => [Caster::PREFIX_VIRTUAL . 'cdata' => $data->cdata],
            self::isPointer($type) => self::castFFIPointer($stub, $type, $data),
            self::isFunction($type) => self::castFFIFunction($stub, $type),
            self::isStructLike($type) => self::castFFIStructLike($stub, $type, $data),
            self::isArray($type) => self::castFFIArrayType($stub, $type, $data),
            default => $args,
        };
    }

    private static function castFFIStringValue(CData $data): string|CutStub
    {
        $result = [];

        for ($i = 0; $i < self::MAX_STRING_LENGTH; ++$i) {
            $result[$i] = $data[$i];

            if ($result[$i] === "\0") {
                break;
            }
        }

        return \implode('', $result);
    }

    /**
     * @param CType $type
     * @param CData $data
     * @return array<string>
     */
    private static function arrayValues(CType $type, CData $data): array
    {
        $result = [];

        for ($i = 0, $size = $type->getArrayLength(); $i < $size; ++$i) {
            $result[] = $data[$i];
        }

        return $result;
    }

    private static function castFFIArrayType(Stub $stub, CType $type, CData $data): array
    {
        $of = $type->getArrayElementType();

        if ($of->getKind() === CType::TYPE_CHAR) {
            $stub->type = Stub::TYPE_STRING;
            $stub->value = \implode('', self::arrayValues($type, $data));
            $stub->class = Stub::STRING_BINARY;

            return [];
        }

        $stub->type = Stub::TYPE_ARRAY;
        $stub->value = $type->getName();
        $stub->class = $type->getArrayLength();

        return self::arrayValues($type, $data);
    }

    private static function castFFIPointer(Stub $stub, CType $type, CData $data): mixed
    {
        $stub->class = $type->getName();
        $reference = $type->getPointerType();

        if (self::isStructLike($reference)) {
            return self::castFFIStructLike($stub, $reference, $data[0]);
        }

        if (self::isFunction($reference)) {
            return self::castFFIFunction($stub, $reference);
        }

        if ($reference->getKind() === CType::TYPE_CHAR) {
            $stub->class .= ' (unsafe access)';

            if ($_SERVER['VAR_DUMPER_FFI_UNSAFE'] ?? false) {
                $stub->type = Stub::TYPE_STRING;
                $stub->value = self::castFFIStringValue($data);
                $stub->class = Stub::STRING_BINARY;

                return [];
            }
        }

        return [0 => $data[0]];
    }

    private static function castFFIFunction(Stub $stub, CType $type): array
    {
        $stub->class = self::funcToString($type);

        return [Caster::PREFIX_VIRTUAL . 'returnType' => $type->getFuncReturnType()];
    }

    private static function castFFIStructLike(Stub $stub, CType $type, CData $data): array
    {
        $result = [];

        foreach ($type->getStructFieldNames() as $name) {
            $field = $type->getStructFieldType($name);

            if (self::isStructLike($field) || self::isPointerToStructLike($field)) {
                $result[Caster::PREFIX_VIRTUAL . $name] = $data->{$name};
            } else {
                $result[Caster::PREFIX_VIRTUAL . $name . '<' . $field->getName() . '>'] = $data->{$name};
            }
        }

        return $result;
    }
}
