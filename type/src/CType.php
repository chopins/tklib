<?php

namespace Toknot\Type;

use Toknot\Type;

define('HAS_FFI', class_exists('FFI'));
define('INT_HOST_LEN', CBinary::getIntSize());
define('BYTE_HOST_ORDER', CBinary::getHostByteOrder());
abstract class CType
{
    final const INT_HOST_LEN = INT_HOST_LEN;
    final const BIG_ENDIAN = 'B';
    final const LITTLE_ENDIAN = 'L';
    final const HOST_ORDER = BYTE_HOST_ORDER;
    final const NETWORK_ORDER = self::BIG_ENDIAN;
    protected $value;
    public function __construct($value)
    {
        $this->value = $value;
    }
    public function __toString()
    {
        return $this->getValue();
    }
    public function getValue()
    {
        return $this->value;
    }
}
abstract class CBinary
{
    public static $hostByteOrder = '';
    public static $intSize = 0;
    final public function __construct(string $binary)
    {
        $members = (new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC);
        $order = $this->getBitOrder();

        $offset = 0;
        foreach ($members as $m) {
            $name = $m->name;
            $typeName = $type->getName();
            $typeName = $type->getName();
            if (is_subclass_of($typeName, CType::class)) {
                throw \TypeError('C memeber type must be sub of CType');
            }

            if ($typeName == char::class || $typeName == uchar::class) {
                $order = CType::BIG_ENDIAN;
            }

            $code = constant("$typeName::F_{$order}_CODE");

            $len = constant("$typeName::LEN");

            $this->$name = new $typeName(unpack($code, $binary, $offset)[1]);
            $offset += $len;
        }
    }

    public static function getIntSize()
    {
        if(self::$intSize) {
            return self::$intSize;
        }
        if (!HAS_FFI) {
            return PHP_INT_SIZE;
        }
        return FFI::sizeof(\FFI::type('int'));
    }
    public static function getHostByteOrder()
    {
        if(self::$hostByteOrder) {
            return self::$hostByteOrder;
        }
        if (!HAS_FFI) {
            return CType::LITTLE_ENDIAN;
        }
        $obj = FFI::cdef();
        $int = $obj->new(" unsigned int");
        $int->cdata = 1;
        $char = FFI::cast('char*', FFI::addr($int));
        return $char->cdata == 1 ? CType::LITTLE_ENDIAN : CType::BIG_ENDIAN;
    }

    public function getByteOrder() {
        return CType::BIG_ENDIAN;
    }
}

class char extends CType
{
    const LEN = 1;
    const F_B_CODE = 'c';
}
class int16 extends CType
{
    const LEN = 2;
    const F_H_CODE = 's';
}
class int_t extends CType
{
    const LEN = self::INT_HOST_LEN;
    const F_H_CODE = 'i';
    const C_NAME = 'int';
}
class uint extends CType
{
    const LEN = self::INT_HOST_LEN;
    const F_H_CODE = 'I';
    const C_NAME = 'unsigned int';
}
class int32 extends CType
{
    const LEN = 4;
    const F_H_CODE = 'l';
}
class int64 extends CType
{
    const LEN = 8;
    const F_H_CODE = 'q';
}

class uchar extends CType
{
    const LEN = 1;
    const F_B_CODE = 'C';
}
class uint16 extends CType
{
    const LEN = 2;
    const F_H_CODE = 'S';
    const F_L_CODE =  'v';
    const F_B_CODE = 'n';
}
class uint32 extends CType
{
    const LEN = 4;
    const F_H_CODE = 'L';
    const F_B_CODE = 'N';
    const F_L_CODE = 'V';
}
class uint64 extends CType
{
    const LEN = 8;
    const F_H_CODE = 'Q';
    const F_B_CODE = 'J';
    const F_L_CODE = 'P';
}

class float32 extends CType
{
    const LEN = 4;
    const F_H_CODE = 'f';
    const F_B_CODE = 'G';
    const F_L_CODE = 'g';
    const C_NAME = 'float';
}
class float64 extends CType
{
    const LEN = 8;
    const F_H_CODE = 'd';
    const F_B_CODE = 'E';
    const F_L_CODE = 'e';
    const C_NAME = 'double';
}


class DNS extends CBinary
{
    public uint16 $transId;
    public uint16 $flags;
    public uint16 $questionCount;
    public uint16 $answerCount;
    public uint16 $authorityCount;
    public uint16 $additionalCount;
    public function getByteOrder()
    {
        return self::NETWORK_ORDER;
    }
}
