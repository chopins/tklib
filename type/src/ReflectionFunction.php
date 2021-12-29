<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2021 Toknot.com
 * @license    http://toknot.com/GPL-2,0.txt GPL-2.0
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

use ReflectionFunction as PhpReflectionFunction;

/**
 * FunctionParameter
 *
 * @author chopin
 */
class ReflectionFunction extends PhpReflectionFunction
{

    protected $parameterReflectionArray = [];
    protected $paramterNames = [];

    public function cacheParameterReflection()
    {
        if(!$this->parameterReflectionArray) {
            $this->parameterReflectionArray = $this->getParameters();
        }
    }

    public function hasParamName($name)
    {
        $this->cacheParameterReflection();
        foreach($this->parameterReflectionArray as $k => $p) {
            if($p->getName() === $name) {
                return $k;
            }
        }
        return false;
    }

    public function hasType($pos, $typeName)
    {
        $this->cacheParameterReflection();
        $fptye = $this->parameterReflectionArray[$pos]->getType();
        $firstParamIsString = false;
        if($fptye instanceof \ReflectionUnionType) {
            foreach($fptye->getTypes() as $utype) {
                if($utype->getName() === $typeName) {
                    return true;
                }
            }
        } elseif($fptye->getName() === $typeName) {
            return true;
        }
        return false;
    }

}
