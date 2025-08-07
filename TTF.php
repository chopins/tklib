<?php

class TTF
{

    public $offset = [];
    public $directory = [];
    public $fontData = '';
    public $requiredTable;
    public $optionalTable;
    public $otherTable;
    public function __construct($font)
    {
        $this->fontData = file_get_contents($font);
    }


    public static function getFormat($format)
    {
        $f = [];
        foreach ($format as $n => $f) {
            if (is_array($f) && array_is_list($f)) {
                $len = $f[1];
                if (is_string($f[0])) {
                    $f[] = "{$f[0]}{$len}$n";
                } else {
                    $f = array_merge($f, self::getFormat($f[0])); //处理数组问题
                }
            } elseif (is_array($f)) {
                $f = array_merge($f, self::getFormat($f));
            } else {
                $f[] = "$f$n";
            }
        }
        return join('/', $f);
    }
    public function getOffsetTable()
    {
        $this->offset = unpack(self::getFormat(self::offsetTableFormat), $this->fontData);
    }

    public function getTimeSeconds($date)
    {
        $timezone = new DateTimeZone('UTC');
        $start = (new DateTime('1904-01-01 00:00:00', $timezone))->getTimestamp();
        $date = (new DateTime($date, $timezone))->getTimestamp();
        return $date - $start;
    }
    public function nowTimeSeconds()
    {
        $timezone = new DateTimeZone('UTC');
        $start = (new DateTime('1904-01-01 00:00:00', $timezone))->getTimestamp();
        return time() - $start;
    }

    public function getTableDirectory()
    {
        $offset = 12;
        $format = self::getFormat(self::tableDirectoryFormat);
        for ($i = 0; $i < $this->offset['numTables']; $i++) {
            $this->directory[] = unpack($format, $this->fontData, $offset);
            $offset += 16;
        }
    }

    public function getTables()
    {
        foreach ($this->directory as $table) {
            $tag = $table['tag'];
            $format = self::getFormat(self::tableFormat[$tag]);
            $data = unpack($format, $this->fontData, $table['offset']);
            if (in_array($tag, self::requiredTableTag)) {
                $this->requiredTable[$tag] = $data;
            } else if (in_array($tag, self::optionalTableTag)) {
                $this->optionalTable[$tag] = $data;
            } else {
                $this->otherTable[$tag] = $data;
            }
        }
    }

    public function calChecksum($tableStart, $tableLength)
    {
        $sum = 0;
        $end = $tableStart + (($tableLength + 3) & ~3) / 4;
        while ($tableStart < $end) {
            $sum += $tableStart++;
        }
        return $sum;
    }

    public const requiredTableTag = ['head', 'maxp', 'loca', 'glyf', 'hmtx', 'cmap'];
    public const optionalTableTag = ['hhea', 'vmtx', 'vhea', 'name', 'OS/2', 'post', 'GSUB', 'GPOS'];
    public const TYPE_FORMAT = [
        'uint8' => 'C',
        'int8' => 'c',
        'uint16' => 'n',
        'int16' => '',
        'uint24' => 'nC',
        'uint32' => 'N',
        'int32' => 'l',
        'Fixed' => 'c4',
        'FWORD' => 's',
        'UFWORD' => 'n',
        'F2DOT14' => 'c2',
        'LONGDATETIME' => 'q', //1904-01-01 00:00:00 GMT/UTC
        'Tag' => 'C4',
        'Offset8' => 'C',
        'Offset16' => 'n',
        'Offset24' => 'nC',
        'Offset32' => 'N',
        'Version16Dot16' => 'c4',
        'Tuple' => [
            'coordinates' => 'F2DOT14', //[axisCount]
        ],
        'TupleVariationHeader' => [
            'variationDataSize' => 'uint16',
            'tupleIndex' => 'uint16',
            'peakTuple' => 'Tuple',
            'intermediateStartTuple' => 'Tuple',
            'intermediateEndTuple' => 'Tuple',
        ],
        'ValueRecord' => [
            'xPlacement' => 'int16',
            'yPlacement' => 'int16',
            'xAdvance' => 'int16',
            'yAdvance' => 'int16',
            'xPlaDeviceOffset' => 'Offset16',
            'yPlaDeviceOffset' => 'Offset16',
            'xAdvDeviceOffset' => 'Offset16',
            'yAdvDeviceOffset' => 'Offset16',
        ],
    ];
    public const offsetTableFormat = [
        'sfntVersion' => 'uint32', //0x00010000   OTTO
        'numTables' => 'uint16',
        'searchRange' => 'uint16', //(max2^n <= numTables) * 16
        'entrySelector' => 'uint16', //log2(max2^n)
        'rangeShift' => 'uint16' //numTables * 16 - searchRange
    ];
    public const tableDirectoryFormat = [
        'tag' => 'Tag',
        'checkSum' => 'uint32',
        'offset' => 'Offset32',
        'length' => 'uint32'
    ];

    public const tableFormat = [
        'head' => [
            'majorVersion' => 'uint16', //1
            'minorVersion' => ' uint16', //0
            'fontRevision' => 'Fixed',
            'checkSumAdjustment' => 'uint32',
            'magicNumber' => 'uint32', //0x5F0F3CF5
            'flags' => 'uint16',
            'unitsPerEm' => 'uint16', //2048  1000
            'created' => 'LONGDATETIME',
            'modified' => 'LONGDATETIME',
            'xMin' => 'int16',
            'yMin' => 'int16',
            'xMax' => 'int16',
            'yMax' => 'int16',
            'macStyle' => 'uint16',
            'lowestRecPPEM' => 'uint16',
            'fontDirectionHint' => 'int16', //0-2
            'indexToLocFormat' => 'int16', //0=短, 1=长
            'glyphDataFormat' => 'int16' //0
        ],
        'maxp' => [
            'version' => 'Version16Dot16', //0x00005000
            'numGlyphs' => 'uint16',
            'maxPoints' => 'uint16',
            'maxContours' => 'uint16',
            'maxCompositePoints' => 'uint16',
            'maxCompositeContours' => 'uint16',
            'maxZones' => 'uint16', //通常为2
            'maxTwilightPoints' => 'uint16',
            'maxStorage' => 'uint16',
            'maxFunctionDefs' => 'uint16',
            'maxInstructionDefs' => 'uint16',
            'maxStackElements' => 'uint16',
            'maxSizeOfInstructions' => 'uint16',
            'maxComponentElements' => 'uint16',
            'maxComponentDepth' => 'uint16',
        ],
        'hhea' => [
            'majorVersion' => 'uint16', //0x0001000
            'minorVersion' => 'uint16',
            'ascent' => 'FWORD',
            'descent' => 'FWORD',
            'lineGap' => 'FWORD',
            'advanceWidthMax' => 'UFWORD',
            'minLeftSideBearing' => 'FWORD',
            'minRightSideBearing' => 'FWORD',
            'xMaxExtent' => 'FWORD',
            'caretSlopeRise' => 'int16',
            'caretSlopeRun' => 'int16',
            'caretOffset' => 'int16',
            'reserved0' => 'int16', //0
            'reserved1' => 'int16', //0
            'reserved2' => 'int16', //0
            'reserved3' => 'int16', //0
            'metricDataFormat' => 'int16', //0
            'numberOfHMetrics' => 'uint16' //hmtx表条目数
        ],
        /**定义字符到字体索引 变长**/
        'cmap' => [
            'version' => 'uint16',
            'numTables' => 'uint16',
            'EncodingRecord' => [
                'platformID' => 'uint16', //0-unicodel; 1-macintosh; 2-ISO; 3-Windows; 4-Custom
                'encodingID' => 'uint16',
                'subtableOffset' => 'Offset32',
            ],
            'SubtableFormat0' => [
                'format' => 'uint16', //0
                'length' => 'uint16',
                'language' => 'uint16',
                'glyphIdArray' => ['uint8', 256], //[256]
            ],
            'SubtableFormat2' => [
                'format' => 'uint16', //2
                'length' => 'uint16',
                'language' => 'uint16',
                'subHeaderKeys' => ['uint16', 256], //[256]
                'subHeaders' => [
                    'firstCode' => 'uint16',
                    'entryCount' => 'uint16',
                    'idDelta' => 'int16',
                    'idRangeOffset' => 'int16',
                ],
                'glyphIdArray' => ['uint16'], //[]
            ],
            'SubtableFormat4' => [
                'format' => 'uint16', //4
                'length' => 'uint16',
                'language' => 'uint16',
                'segCountX2' => 'uint16', //2 x segCount
                'searchRange' => 'uint16',
                'entrySelector' => 'uint16',
                'rangeShift' => 'uint16',
                'endCode' => ['uint16', 'segCount'], //endCode[segCount]
                'reservedPad' => 'uint16', //0
                'startCode' => ['uint16', 'segCount'], //startCode[segCount]
                'idDelta' => ['int16', 'segCount'], //idDelta[segCount]
                'idRangeOffset' => 'uint16',
                'glyphIdArray' => ['uint16'] //[]
            ],
            'SubtableFormat6' => [
                'format' => 'uint16', //6
                'length' => 'uint16',
                'language' => 'uint16',
                'firstCode' => 'uint16',
                'entryCount' => 'uint16',
                'glyphIdArray' => ['uint16', 'entryCount'] //[entryCount]
            ],
            'SubtableFormat8' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'is32' => ['uint8', 8192], //[8192]
                'numGroups' => 'uint32',
                'groups' => [ //[numGroups]
                    [
                        'startCharCode' => 'uint32',
                        'endCharCode' => 'uint32',
                        'startGlyphID' => 'uint32',
                    ],
                    'numGroups'
                ]
            ],
            'SubtableFormat10' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'startCharCode' => 'uint32',
                'numChars' => 'uint32',
                'glyphIdArray' => ['uint16'], //[]
            ],
            'SubtableFormat12' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'numGroups' => 'uint32',
                'groups' => [ //[numGroups]
                    [
                        'startCharCode' => 'uint32',
                        'endCharCode' => 'uint32',
                        'startGlyphID' => 'uint32'
                    ],
                    'numGroups'
                ]
            ],
            'SubtableFormat13' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'numGroups' => 'uint32',
                'groups' => [ //[numGroups]
                    [
                        'startCharCode' => 'uint32',
                        'endCharCode' => 'uint32',
                        'glyphID' => 'uint32'
                    ],
                    'numGroups'
                ]
            ],
            'SubtableFormat14' => [
                'format' => 'uint16', //8
                'length' => 'uint32',
                'numVarSelectorRecords' => 'uint32',
                'varSelector' => [ //[numVarSelectorRecords]
                    [
                        'varSelector' => 'uint24',
                        'defaultUVSOffset' => 'Offset32',
                        'nonDefaultUVSOffset' => 'Offset32'
                    ],
                    'numVarSelectorRecords'
                ],
                'defaultUVSTable' => [
                    'numUnicodeValueRanges' => 'uint32',
                    'ranges' => [ //[numUnicodeValueRanges]
                        [
                            'startUnicodeValue' => 'uint24',
                            'additionalCount' => 'uint8',
                        ],
                        'numUnicodeValueRanges'
                    ]
                ],
                'nonDefaultUVStable' => [
                    'numUVSMappings' => 'uint32',
                    'uvsMappings' => [ //[numUVSMappings]
                        [
                            'unicodeValue' => 'uint24',
                            'glyphID' => 'uint16',
                        ],
                        'numUVSMappings'
                    ]
                ]
            ],

        ],

        /**变长**/
        'loca' => [
            'shortFormat' => [
                'offsets' => ['Offset16', 'numGlyphs + 1'] //[numGlyphs + 1]
            ],
            'longFormat' => [
                'offsets' => ['Offset32', 'numGlyphs + 1'] //[numGlyphs + 1]
            ],
        ],
        /**变长**/
        'glyf' => [
            'simple' => [
                'numberOfContours' => 'int16',
                'xMin' => 'int16',
                'yMin' => 'int16',
                'xMax' => 'int16',
                'yMax' => 'int16',
                'endPtsOfContours' => ['uint16', 'numberOfContours'],
                'instructionLength' => 'uint16',
                'instructions' => ['uint8', 'instructionLength'], //[instructionLength]
                'flags' => ['uint8', 'variable'], //[variable]
                'XCoordinates' => ['uint8|int16',  'variable'], //[variable] 类型取决与 flags 是否设置 X_SHORT_VECTOR 与 X_IS_SAME_OR_POSITIVE_X_SHORT_VECTOR
                'YCoordinates' => ['uint8|int16',  'variable'] //[variable]
            ],
            'complex' => [
                'numberOfContours' => 'int16', //-1
                'xMin' => 'int16',
                'yMin' => 'int16',
                'xMax' => 'int16',
                'yMax' => 'int16',
                'flags' => 'uint16',
                'glyphIndex' => 'uint16',
                'argument1' => 'uint8|uint16|int8|int16',
                'argument2' => 'uint8|uint16|int8|int16',
                'transform'
            ]
        ],
        /**变长**/
        'hmtx' => [
            'hMetrics' => [ //[numberOfHMetrics]
                [
                    'advanceWidth' => 'UFWORD',
                    'lsb' => 'FWORD',
                ],
                'numberOfHMetrics'
            ],
            'leftSideBearings' => ['FWORD', 'numGlyphs - numberOfHMetrics'] //[numGlyphs -numberOfHMetrics]
        ],
        /**变长**/
        'name' => [
            'version0' => [
                'version' => 'uint16',
                'count' => 'uint16',
                'storageOffset' => 'Offset16',
                'nameRecord' => [ //[count]
                    '(Variable)',
                    'count',
                ]
            ],
            'version1' => [
                'version' => 'uint16',
                'count' => 'uint16',
                'storageOffset' => 'Offset16',
                'nameRecord' => [ //[count]
                    [
                        'platformID' => 'uint16',
                        'encodingID' => 'uint16',
                        'languageID' => 'uint16',
                        'nameID' => 'uint16',
                        'length' => 'uint16',
                        'stringOffset' => 'Offset16'
                    ],
                    'count'
                ],
                'langTagCount' => 'uint16',
                'langTagRecord' => [
                    [
                        'length' => 'uint16',
                        'langTagOffset' => 'Offset16'
                    ],
                    'langTagCount'
                ],
                '(Variable)'
            ],
        ],
        /*96*/
        'OS/2' => [
            'version' => 'uint16',
            'xAvgCharWidth' => 'FWORD',
            'usWeightClass' => 'uint16',
            'usWidthClass' => 'uint16',
            'fsType' => 'uint16',
            'ySubscriptXSize' => 'FWORD',
            'ySubscriptYSize' => 'FWORD',
            'ySubscriptXOffset' => 'FWORD',
            'ySubscriptYOffset' => 'FWORD',
            'ySuperscriptXSize' => 'FWORD',
            'ySuperscriptYSize' => 'FWORD',
            'ySuperscriptXOffset' => 'FWORD',
            'ySuperscriptYOffset' => 'FWORD',
            'yStrikeoutSize' => 'FWORD',
            'yStrikeoutPosition' => 'FWORD',
            'sFamilyClass' => 'int16',
            'panose' => ['uint8', 10], //[10]
            'ulUnicodeRange1' => 'uint32',
            'ulUnicodeRange2' => 'uint32',
            'ulUnicodeRange3' => 'uint32',
            'ulUnicodeRange4' => 'uint32',
            'achVendID' => 'Tag',
            'fsSelection' => 'uint16',
            'usFirstCharIndex' => 'uint16',
            'usLastCharIndex' => 'uint16',
            'sTypoAscender' => 'FWORD',
            'sTypoDescender' => 'FWORD',
            'sTypoLineGap' => 'FWORD',
            'usWinAscent' => 'UFWORD',
            'usWinDescent' => 'UFWORD', //version0
            'ulCodePageRange1' => 'uint32', //version 1 add
            'ulCodePageRange2' => 'uint32', //version 1 add
            'sxHeight' => 'FWORD', //version2 add
            'sCapHeight' => 'FWORD', //version2 add
            'usDefaultChar' => 'uint16', //version2 add
            'usBreakChar' => 'uint16', //version2 add
            'usMaxContext' => 'uint16', //version2 add
            'usLowerOpticalPointSize' => 'uint16', //version 5 add
            'usUpperOpticalPointSize' => 'uint16', //version 5 add
        ],
        /**变长**/
        'post' => [
            'version' => 'Version16Dot16', //support version 1.0,2.0,3.0, not support version 2.5 and 4.0
            'italicAngle' => 'Fixed',
            'underlinePosition' => 'FWORD',
            'underlineThickness' => 'FWORD',
            'isFixedPitch' => 'uint32',
            'minMemType42' => 'uint32',
            'maxMemType42' => 'uint32',
            'minMemType1' => 'uint32',
            'maxMemType1' => 'uint32',
            'numGlyphs' => 'uint16', //For version 2.0
            'glyphNameIndex' => ['uint16', 'numGlyphs'], //[numGlyphs] For version 2.0
            'stringData' => ['uint8', 'variable'] //[variable]  For version 2.0
        ],
        'vhea' => [
            'majorVersion' => 'uint16',
            'minorVersion' => 'uint16',
            'ascender' => 'FWORD',
            'descender' => 'FWORD',
            'lineGap' => 'FWORD',
            'advanceWidthMax' => 'UFWORD',
            'minLeftSideBearing' => 'FWORD',
            'minRightSideBearing' => 'FWORD',
            'xMaxExtent' => 'FWORD',
            'caretSlopeRise' => 'int16',
            'caretSlopeRun' => 'int16',
            'caretOffset' => 'int16',
            'reserved0' => 'int16',
            'reserved1' => 'int16',
            'reserved2' => 'int16',
            'reserved3' => 'int16',
            'metricDataFormat' => 'int16',
            'numberOfHMetrics' => 'uint16',
        ],
        'meta' => [
            'version' => 'uint32',
            'flags' => 'uint32',
            'reserved' => 'uint32',
            'dataMapsCount' => 'uint32',
            'dataMaps' => [
                [
                    'tag' => 'Tag',
                    'dataOffset' => 'Offset32',
                    'dataLength' => 'uint32'
                ],
                'dataMapsCount'
            ]
        ],
        'cvar' => [
            'majorVersion' => 'uint16',
            'minorVersion' => 'uint16',
            'tupleVariationCount' => 'uint16',
            'dataOffset' => 'Offset16',
            'tupleVariationHeaders' => ['TupleVariationHeader', 'tupleVariationCount'],  //[tupleVariationCount]
        ],
        'avar' => [
            'majorVersion' => 'uint16',
            'minorVersion' => 'uint16',
            'reserved' => 'uint16',
            'axisCount' => 'uint16',
            'axisSegmentMaps' => [ //[axisCount]
                [
                    'positionMapCount' => 'uint16',
                    'axisValueMaps' => [ //[positionMapCount]
                        [
                            'fromCoordinate' => 'F2DOT14',
                            'toCoordinate'  => 'F2DOT14',
                        ],
                        'positionMapCount'
                    ]
                ],
                'axisCount'
            ]
        ],
        'cvt' => ['FWORD', 'n'], //[n]
        'fpgm' => ['uint8', 'n'], //[n]
        'prep' => ['uint8', 'n'], //[n]
        'sbix' => [
            'version' => 'uint16',
            'flags' => 'uint16',
            'numStrikes' => 'uint32',
            'strikeOffsets' => ['Offset32', 'numStrikes'], //[numStrikes]
            'strike' => [
                'ppem' => 'uint16',
                'ppi' => 'uint16',
                'glyphDataOffsets' => ['Offset32', 'numGlyphs+1'], //[numGlyphs+1]
                'glyph' => [
                    'originOffsetX' => 'int16',
                    'originOffsetY' => 'int16',
                    'graphicType' => 'Tag',
                    'data' => ['uint8'], //[]
                ]
            ],
        ],
        'vmtx' => [
            'advanceHeight' => 'UFWORD',
            'topSideBearing' => ['FWORD'],
        ],
        'kern' => [
            'version',
            'nTables',
            'Format0' => [
                'version' => 'uint16',
                'length' => 'uint16',
                'coverage' => 'uint16',
                'nPairs' => 'uint16',
                'searchRange' => 'uint16',
                'entrySelector' => 'uint16',
                'rangeShift' => 'uint16',
                'kernPairs' => [ //[nPairs]
                    [
                        'left' => 'uint16',
                        'right' => 'uint16',
                        'value' => 'FWORD'
                    ],
                    'nPairs'
                ]
            ],
            'Format2' => [
                'version' => 'uint16',
                'length' => 'uint16',
                'coverage' => 'uint16',
                'rowWidth' => 'uint16',
                'leftClassOffset' => 'Offset16',
                'rightClassOffset' => 'Offset16',
                'kerningArrayOffset' => 'Offset16',
                'firstGlyph' => 'uint16',
                'nGlyphs' => 'uint16'
            ]
        ],
        'hdmx' => [
            'version' => 'uint16',
            'numRecords' => 'uint16',
            'sizeDeviceRecord' => 'uint32',
            'records' => [ //[numRecords]
                [
                    'pixelSize' => 'uint8',
                    'maxWidth' => 'uint8',
                    'widths' => ['uint8', 'numGlyphs'], //[numGlyphs]
                ],
                'numRecords'
            ]
        ],
        'gvar' => [
            'majorVersion' => 'uint16',
            'minorVersion' => 'uint16',
            'axisCount' => 'uint16',
            'sharedTupleCount' => 'uint16',
            'sharedTuplesOffset' => 'Offset32',
            'glyphCount' => 'uint16',
            'flags' => 'uint16',
            'glyphVariationDataArrayOffset' => 'Offset32',
            'glyphVariationDataOffsets' => ['Offset16|Offset32', 'glyphCount + 1'], //[glyphCount + 1]
            'sharedTuples' => ['Tuple', 'sharedTupleCount'], //[sharedTupleCount]
            'GlyphVariationData' => [
                'tupleVariationCount' => 'uint16',
                'dataOffset' => 'Offset16',
                'tupleVariationHeaders' => ['TupleVariationHeader', 'tupleCount'], //[tupleCount]
            ]
        ],
        'gasp' => [
            'version' => 'uint16',
            'numRanges' => 'uint16',
            'gaspRanges' => [ //[numRanges]
                [
                    'rangeMaxPPEM' => 'uint16',
                    'rangeGaspBehavior' => 'uint16'
                ],
                'numRanges'
            ]
        ],
        'GSUB' => [
            'version1.0' => [
                'majorVersion' => 'uint16',
                'minorVersion' => 'uint16',
                'scriptListOffset' => 'Offset16',
                'featureListOffset' => 'Offset16',
                'lookupListOffset' => 'Offset16'
            ],
            'version1.1' => [
                'majorVersion' => 'uint16',
                'minorVersion' => 'uint16',
                'scriptListOffset' => 'Offset16',
                'featureListOffset' => 'Offset16',
                'lookupListOffset' => 'Offset16',
                'featureVariationsOffset' => 'Offset32',
                'SingleSubstFormat1' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'deltaGlyphID' => 'int16'
                ],
                'SingleSubstFormat2' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'glyphCount' => 'uint16',
                    'substituteGlyphIDs' => ['uint16', 'glyphCount'], //[glyphCount]
                ],
                'MultipleSubstFormat1' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'sequenceCount' => 'uint16',
                    'sequenceOffsets' => 'Offset16',
                    'SequenceTable' => [
                        'glyphCount' => 'uint16',
                        'substituteGlyphIDs' => 'uint16'
                    ],
                    'AlternateSubstFormat1' => [
                        'format' => 'uint16',
                        'coverageOffset' => 'Offset16',
                        'alternateSetCount' => 'uint16',
                        'alternateSetOffsets' => ['Offset16', 'alternateSetCount'], //[alternateSetCount]
                        'AlternateSetTable' => [
                            'glyphCount' => 'uint16',
                            'alternateGlyphIDs' => ['uint16', 'glyphCount'], //[glyphCount]
                        ]
                    ],
                    'LigatureSubstFormat1' => [
                        'format' => 'uint16',
                        'coverageOffset' => 'Offset16',
                        'ligatureSetCount' => 'uint16',
                        'ligatureSetOffsets' => ['Offset16', 'ligatureSetCount'], //[ligatureSetCount]
                        'LigatureSetTable' => [
                            'ligatureCount' => 'uint16',
                            'ligatureOffsets' => ['Offset16', 'LigatureCount'], //[LigatureCount]
                        ],
                        'LigatureTable' => [
                            'ligatureGlyph' => 'uint16',
                            'componentCount' => 'uint16',
                            'componentGlyphIDs' => ['uint16', 'componentCount -1'], //[componentCount -1]
                        ],
                        'SubstExtensionFormat1' => [
                            'format' => 'uint16',
                            'extensionLookupType' => 'uint16',
                            'extensionOffset' => 'Offset32',
                        ],
                        'ReverseChainSingleSubstFormat1' => [
                            'format' => 'uint16',
                            'coverageOffset' => 'Offset16',
                            'backtrackGlyphCount' => 'uint16',
                            'backtrackCoverageOffsets' => ['Offset16', 'backtrackGlyphCount'], //[backtrackGlyphCount]
                            'lookaheadGlyphCount' => 'uint16',
                            'lookaheadCoverageOffsets' => ['Offset16', 'lookaheadGlyphCount'], //[lookaheadGlyphCount]
                            'glyphCount' => 'uint16',
                            'substituteGlyphIDs' => ['uint16', 'glyphCount'], //[glyphCount]
                        ],
                    ],
                ]
            ]
        ],
        'GPOS' => [
            'version1.0' => [
                'majorVersion' => 'uint16',
                'minorVersion' => 'uint16',
                'scriptListOffset' => 'Offset16',
                'featureListOffset' => 'Offset16',
                'lookupListOffset' => 'Offset16',
            ],
            'version1.1' => [
                'majorVersion' => 'uint16',
                'minorVersion' => 'uint16',
                'scriptListOffset' => 'Offset16',
                'featureListOffset' => 'Offset16',
                'lookupListOffset' => 'Offset16',
                'featureVariationsOffset' => 'Offset32',
                'SinglePosFormat1' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'valueFormat' => 'uint16',
                    'valueRecord' => 'ValueRecord'
                ],
                'SinglePosFormat2' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'valueFormat' => 'uint16',
                    'valueCount' => 'uint16',
                    'valueRecords' => ['ValueRecord', 'valueCount'] //[valueCount]
                ],
                'PairPosFormat1' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'valueFormat1' => 'uint16',
                    'valueFormat2' => 'uint16',
                    'pairSetCount' => 'uint16',
                    'pairSetOffsets' => 'Offset16',
                    'PairSetTable' => [
                        'pairValueCount' => 'uint16',
                        'pairValueRecords' => [ //[pairValueCount]
                            [
                                'secondGlyph' => 'uint16',
                                'valueRecord1' => 'ValueRecord',
                                'valueRecord2' => 'ValueRecord'
                            ],
                            'pairValueCount'
                        ],
                    ]
                ],
                'PairPosFormat2' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'valueFormat1' => 'uint16',
                    'valueFormat2' => 'uint16',
                    'classDef1Offset' => 'Offset16',
                    'classDef2Offset' => 'Offset16',
                    'class1Count' => 'uint16',
                    'class2Count' => 'uint16',
                    'class1Records' => [ //[class1Count]
                        [
                            'class2Records' => [ //[class2Count]
                                [
                                    'valueRecord1' => 'ValueRecord',
                                    'valueRecord2' => 'ValueRecord'
                                ],
                                'class2Count'
                            ],
                        ],
                        'class1Count'
                    ]
                ],
                'PairPosFormat2' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'valueFormat1' => 'uint16',
                    'valueFormat2' => 'uint16',
                    'classDef1Offset' => 'Offset16',
                    'classDef2Offset' => 'Offset16',
                    'class1Count' => 'uint16',
                    'class2Count' => 'uint16',
                    'class1Records' => [ //[class1Count]
                        [
                            'class2Records' => [ //[class2Count]
                                [
                                    'valueRecord1' => 'ValueRecord',
                                    'valueRecord2' => 'ValueRecord',
                                ],
                                'class2Count'
                            ]
                        ],
                        'class1Count'
                    ]
                ],
                'CursivePosFormat1' => [
                    'format' => 'uint16',
                    'coverageOffset' => 'Offset16',
                    'entryExitCount' => 'uint16',
                    'entryExitRecords' => [ //[entryExitCount]
                        [
                            'entryAnchorOffset' => 'Offset16',
                            'exitAnchorOffset' => 'Offset16',
                        ],
                        'entryExitCount'
                    ]
                ],
                'MarkBasePosFormat1' => [
                    'format' => 'uint16',
                    'markCoverageOffset' => 'Offset16',
                    'baseCoverageOffset' => 'Offset16',
                    'markClassCount' => 'uint16',
                    'markArrayOffset' => 'Offset16',
                    'baseArrayOffset' => 'Offset16',
                    'BaseArrayTable' => [
                        'baseCount' => 'uint16',
                        'baseRecords' => [ //[baseCount]
                            ['baseAnchorOffsets' => ['Offset16', 'markClassCount']], //[markClassCount]
                            'baseCount'
                        ]
                    ]
                ],
                'MarkLigPosFormat1' => [
                    'format' => 'uint16',
                    'markCoverageOffset' => 'Offset16',
                    'ligatureCoverageOffset' => 'Offset16',
                    'markClassCount' => 'uint16',
                    'markArrayOffset' => 'Offset16',
                    'ligatureArrayOffset' => 'Offset16',
                    'LigatureArrayTable' => [
                        'ligatureCount' => 'uint16',
                        'ligatureAttachOffsets' => 'Offset16',
                        'LigatureAttachTable' => [
                            'componentCount' => 'uint16',
                            'componentRecords' => [ //[componentCount]
                                [
                                    'ligatureAnchorOffsets' => ['Offset16', 'markClassCount'] //[markClassCount]
                                ],
                                'componentCount'
                            ]
                        ]
                    ]
                ],
                'MarkMarkPosFormat1' => [
                    'format' => 'uint16',
                    'mark1CoverageOffset' => 'Offset16',
                    'mark2CoverageOffset' => 'Offset16',
                    'markClassCount' => 'uint16',
                    'mark1ArrayOffset' => 'Offset16',
                    'mark2ArrayOffset' => 'Offset16',
                    'Mark2ArrayTable' => [
                        'mark2Count' => 'uint16',
                        'mark2Records' => [ //[mark2Count]
                            [
                                'mark2AnchorOffsets' => ['Offset16', 'markClassCount'], //[markClassCount]
                            ],
                            'mark2Count'
                        ]
                    ]
                ],
                'PosExtensionFormat1' => [
                    'format' => 'uint16',
                    'extensionLookupType' => 'uint16',
                    'extensionOffset' => 'Offset32'
                ],
            ],
            'AnchorFormat1' => [
                'format' => 'uint16',
                'xCoordinate' => 'int16',
                'yCoordinate' => 'int16',
            ],
            'AnchorFormat2' => [
                'format' => 'uint16',
                'xCoordinate' => 'int16',
                'yCoordinate' => 'int16',
                'anchorPoint' => 'uint16',
            ],
            'AnchorFormat3' => [
                'format' => 'uint16',
                'xCoordinate' => 'int16',
                'yCoordinate' => 'int16',
                'xDeviceOffset' => 'Offset16',
                'yDeviceOffset' => 'Offset16',
            ],
            'MarkArrayTable' => [
                'markCount' => 'uint16',
                'markRecords' => [ //[markCount]
                    [
                        'markClass' => 'uint16',
                        'markAnchorOffset' => 'Offset16'
                    ],
                    'markCount'
                ]
            ]
        ],
    ];
}
