<?php

class TTF
{
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
        'Version16Dot16' => 'c4'
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
                'glyphIdArray' => 'uint8', //[256]
            ],
            'SubtableFormat2' => [
                'format' => 'uint16', //2
                'length' => 'uint16',
                'language' => 'uint16',
                'subHeaderKeys' => 'uint16', //[256]
                'subHeaders' => [
                    'firstCode' => 'uint16',
                    'entryCount' => 'uint16',
                    'idDelta' => 'int16',
                    'idRangeOffset' => 'int16',
                ],
                'glyphIdArray' => 'uint16', //[]
            ],
            'SubtableFormat4' => [
                'format' => 'uint16', //4
                'length' => 'uint16',
                'language' => 'uint16',
                'segCountX2' => 'uint16', //2 x segCount
                'searchRange' => 'uint16',
                'entrySelector' => 'uint16',
                'rangeShift' => 'uint16',
                'endCode' => 'uint16', //endCode[segCount]
                'reservedPad' => 'uint16', //0
                'startCode' => 'uint16', //startCode[segCount]
                'idDelta' => 'int16', //idDelta[segCount]
                'idRangeOffset' => 'uint16',
                'glyphIdArray' => 'uint16' //[]
            ],
            'SubtableFormat6' => [
                'format' => 'uint16', //6
                'length' => 'uint16',
                'language' => 'uint16',
                'firstCode' => 'uint16',
                'entryCount' => 'uint16',
                'glyphIdArray' => 'uint16' //[entryCount]
            ],
            'SubtableFormat8' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'is32' => 'uint8', //[8192]
                'numGroups' => 'uint32',
                'groups' => [ //[numGroups]
                    'startCharCode' => 'uint32',
                    'endCharCode' => 'uint32',
                    'startGlyphID' => 'uint32'
                ]
            ],
            'SubtableFormat10' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'startCharCode' => 'uint32',
                'numChars' => 'uint32',
                'glyphIdArray' => 'uint16', //[]
            ],
            'SubtableFormat12' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'numGroups' => 'uint32',
                'groups' => [ //[numGroups]
                    'startCharCode' => 'uint32',
                    'endCharCode' => 'uint32',
                    'startGlyphID' => 'uint32'
                ]
            ],
            'SubtableFormat13' => [
                'format' => 'uint16', //8
                'reserved' => 'uint16', //0
                'length' => 'uint32',
                'language' => 'uint32',
                'numGroups' => 'uint32',
                'groups' => [ //[numGroups]
                    'startCharCode' => 'uint32',
                    'endCharCode' => 'uint32',
                    'glyphID' => 'uint32'
                ]
            ],
            'SubtableFormat14' => [
                'format' => 'uint16', //8
                'length' => 'uint32',
                'numVarSelectorRecords' => 'uint32',
                'varSelector' => [ //[numVarSelectorRecords]
                    'varSelector' => 'uint24',
                    'defaultUVSOffset' => 'Offset32',
                    'nonDefaultUVSOffset' => 'Offset32'
                ],
                'defaultUVSTable' => [
                    'numUnicodeValueRanges' => 'uint32',
                    'ranges' => [//[numUnicodeValueRanges]
                        'startUnicodeValue' => 'uint24',
                        'additionalCount' => 'uint8',
                    ]
                ],
                'nonDefaultUVStable' => [
                    'numUVSMappings' => 'uint32',
                    'uvsMappings' => [//[numUVSMappings]
                        'unicodeValue' => 'uint24',
                        'glyphID' => 'uint16',
                    ]
                ]
            ],

        ],

        /**变长**/
        'loca' => [
            'shortFormat' => 'Offset16',
            'longFormat' => 'Offset32',
            'numGlyphs'
        ],
        /**变长**/
        'glyf' => [
            'simple' => [
                'header',
                'contourEndPts',
                'instructionLen',
                'instructions',
                'flags',
                'XCoordinates',
                'YCoordinates'
            ],
            'complex' => [
                'header',
                'component' => [
                    'flags',
                    'glyphIndex',
                    'arg1',
                    'arg2',
                    'transform'
                ],
                'instrunctions'
            ]
        ],
        /**变长**/
        'hmtx' => [
            'advanceWidth' => 'n',
            'lsb' => 'n',
            'lsb' => 'n',
            'advanceWidth'
        ],
        /**变长**/
        'name' => [],
        /*96*/
        'os/2' => [],
        /**变长**/
        'post' => [],
    ];

    public $offset = [];
    public $directory = [];
    public $fontData = '';
    public $tables = [];

    public static function getFormat($format)
    {
        $f = [];
        foreach ($format as $n => $f) {
            $f[] = "$f$n";
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
            $format = self::getFormat(self::tableFormat[$table['tag']]);
            $this->tables[$table['tag']] = unpack($format, $this->fontData, $table['offset']);
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
}
