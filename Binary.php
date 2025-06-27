<?php

abstract class Struct
{
    final public function __construct(
        public readonly mixed $value
    ) {}
}
class Char extends Struct
{
    const FLAG = 'c';
}
class UChar extends Struct
{
    const FLAG = 'C';
}
class Short extends Struct
{
    const FLAG = 's';
}
class UShort extends Struct
{
    const FLAG = 'S';
}
class HUShort extends Struct
{
    const FLAG = 'n';
}
class LUShort extends Struct
{
    const FLAG = 'v';
}
class Integer extends Struct
{
    const FLAG = 'i';
}
class UInt extends Struct
{
    const FLAG = 'I';
}
class Int32 extends Struct
{
    const FLAG = 'l';
}
class UInt32 extends Struct
{
    const FLAG = 'L';
}
class HUInt32 extends Struct
{
    const FLAG = 'N';
}
class LUInt32 extends Struct
{
    const FLAG = 'V';
}
class Long extends Struct
{
    const FLAG = 'q';
}
class ULong extends Struct
{
    const FLAG = 'Q';
}
class HULong extends Struct
{
    const FLAG = 'J';
}
class LULong extends Struct
{
    const FLAG = 'P';
}
class SFloat extends Struct
{
    const FLAG = 'f';
}
class LFloat extends Struct
{
    const FLAG = 'g';
}
class HFloat extends Struct
{
    const FLAG = 'G';
}
class Real extends Struct
{
    const FLAG = 'd';
}
class LDouble extends Struct
{
    const FLAG = 'e';
}
class HDouble extends Struct
{
    const FLAG = 'E';
}
class FNull extends Struct
{
    const FLAG = 'a';
}
class FSpace extends Struct
{
    const FLAG = 'A';
}
class LHex extends Struct
{
    const FLAG = 'h';
}
class HHex extends Struct
{
    const FLAG = 'H';
}
class AddNull extends Struct
{
    const FLAG = 'x';
}
class BS extends Struct
{
    const FLAG = 'X';
}
class FNullSpace extends Struct
{
    const FLAG = 'Z';
}
class PadNull extends Struct
{
    const FLAG  = '@';
}



abstract class Binary
{
    protected static array $typeNames = [];
    protected static string $format = '';
    protected static string $unformat = '';
    public static function getTypeNames()
    {
        if (static::$format) {
            return;
        }
        static::$format = '';
        static::$unformat = '';
        static::$typeNames = [];
        $params = (new ReflectionMethod(static::class, '__construct'))->getParameters();
        foreach ($params as $p) {
            $typeName = $p->getType()->getName();
            if (is_subclass_of($typeName, Type::class)) {
                static::$typeNames[] = $typeName;
                static::$format .= $typeName::FLAG;
                if ($typeName == ULong::class || $typeName == LULong::class || $typeName == HULong::class) {
                    static::$unformat .= Long::FLAG;
                } else {
                    static::$unformat .= $typeName::FLAG;
                }
            }
        }
    }

    public static function new($data)
    {
        static::getTypeNames();
        $a = unpack(static::$unformat, $data);
        $values = [];
        $i = 0;
        foreach ($a as $v) {
            $class = static::$typeNames[$i];
            if (($class == ULong::class || $class == LULong::class || $class == HULong::class) && $v < 0) {
                $flag = GMP_MSW_FIRST;
                if($class == ULong::class) {
                    $flag |= GMP_NATIVE_ENDIAN;
                } else if($class == LULong::class) {
                    $flag |= GMP_LITTLE_ENDIAN;
                } else {
                    $flag |= GMP_BIG_ENDIAN;
                }
                $v = gmp_strval(gmp_import(pack($class::FLAG, $v), 8, $flag));
            }
            $values = new $class($v);
            $i++;
        }
        return new static(...$values);
    }

    public function toBin()
    {
        static::getTypeNames();
        $values = [];
        foreach (static::$typeNames as $name) {
            $values[] = $this->$name->value;
        }
        return pack($format, ...$values);
    }
}
