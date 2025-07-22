<?php

new CAPTCHA;

class CAPTCHA
{
    private $bgImg;
    private $img;
    public function __construct()
    {
        $this->initImage(300, 100);
        $this->draw();
        ob_start();
        imagewebp($this->img);
        $img = base64_encode(ob_get_clean());
        imagedestroy($this->img);
?>
        <html>

        <body>
            <div style="margin: 10px 10px; padding: 15px 10px;">
                <img src="data:image/webp;base64,<?= $img ?>" id="img" style="margin: 60px 100px;">
                <button id="submit" type="button">提交</button>
            </div>
            <div id="pointer" style="border-radius: 12px;color:red;border:2px solid red;width:20px;height:20px;line-height: 20px;text-align: center;font-weight:bold;position: absolute;z-index:10;display:none;cursor: pointer;background-color: #CCC;">0</div>
            <script>
                (function() {
                    let img = document.getElementById('img');
                    let pointer = document.getElementById('pointer');

                    let start = false;
                    let click = [];
                    let lines = [];
                    let path = [];

                    function remove(e) {
                        let p = e.target;
                        let num = parseInt(p.textContent);
                        if (num != click.length) {
                            return;
                        }
                        click.pop();
                        path.pop();
                        p.parentNode.removeChild(p);
                    };
                    img.addEventListener('click', function(e) {
                        click.push([e.offsetX, e.offsetY, e.timeStamp]);
                        path.push(lines);
                        lines = [];
                        let np = pointer.cloneNode();
                        np.textContent = click.length;
                        np.style.left = (e.pageX - 10) + 'px';
                        np.style.top = (e.pageY - 10) + 'px';
                        np.style.display = 'inline-block';
                        np.addEventListener('click', remove);
                        document.body.appendChild(np);
                    });

                    img.addEventListener('mouseenter', function(e) {
                        start = true;
                        lines.push([e.offsetX, e.offsetY, e.timeStamp])
                    });
                    img.addEventListener('mouseleave', function() {
                        start = false;
                        lines = [];
                    })
                    img.addEventListener('mousemove', function(e) {
                        lines.push([e.offsetX, e.offsetY, e.timeStamp])
                    });
                    document.getElementById('submit').addEventListener('click', function(e) {
                        console.log(click);
                        console.log(path);
                    });
                })();
            </script>
        </body>

        </html>
<?php
    }

    public function setBgImg($img)
    {
        $type = mime_content_type($img);
        list($filetype, $ext) = explode('/', $type);
        if ($filetype != 'image') {
            throw new RuntimeException('backgroud image is not image');
        }
        $func = "imagecreatefrom$ext";
        if (!function_exists($func)) {
            throw new RuntimeException('backgroud image type is not support');
        }
        $this->bgImg = $func($img);
    }

    public function initImage($w, $h)
    {
        $this->img = imagecreatetruecolor($w, $h);
        imagealphablending($this->img, true);
        if ($this->bgImg) {
            imagecopyresized($this->img, $this->bgImg, 0, 0, 0, 0, $w, $h, imagesx($this->bgImg), imagesy($this->bgImg));
        } else {
            $bgcolor = imagecolorallocatealpha($this->img, 64, 224, 208, 0);
            imagefilledrectangle($this->img, 0, 0, $w, $h, $bgcolor);
        }
    }

    /**
     * 外圆内矩
     * 外矩内圆型
     * 双圆
     * 椭圆
     * 三角
     * 四边形
     *
     */
    public function draw()
    {
        $pointer1 = [60, 60];
        $pointer2 = [100, 30];
        $
        $r = 0;
        $g = 0;
        $b = 0;
        $lr = 255;
        $lg = 255;
        $lb = 255;
        $color = imagecolorallocate($this->img, $r, $g, $b);
        imagefilledrectangle($this->img, 50, 50, 70, 70, $color);
        $inlinecolor = imagecolorallocate($this->img, $lr, $lg, $lb);
        imagefilledellipse($this->img, 60, 60, 16, 16, $inlinecolor);

        imagefilledellipse($this->img, 100, 30, 20, 20, $color);
        imagefilledrectangle($this->img, 95, 25, 105, 35, $inlinecolor);
    }

    public function drawText()
    {
        $this->charLen = mb_strlen(self::CHAR);
        $num = mt_rand(2, 4);
        switch ($num) {
            case 2:
                $this->drawGlyph(10, $angle, $x, $y, $color,$font);
                $this->drawGlyph(10, $angle, $x + 10, $y, $color,$font);
                break;
            case 3:
                $this->drawGlyph(10, $angle, $x, $y, $color,$font);
                $this->drawGlyph(5, $angle, $x + 5, $y, $color,$font);
                $this->drawGlyph(5, $angle, $x + 5, $y + 5, $color,$font);
                break;
            case 4:
                $this->drawGlyph(5, $angle, $x, $y, $color,$font);
                $this->drawGlyph(5, $angle, $x + 5, $y, $color,$font);
                $this->drawGlyph(5, $angle, $x, $y + 5, $color,$font);
                $this->drawGlyph(5, $angle, $x + 5, $y + 5, $color,$font);
                break;
        }
    }
    public function isList(array $a, $type)
    {
        $keys = array_keys($a);
        $i = 0;
        foreach ($keys as $v) {
            if ($v != $i) {
                return false;
            }
            if (gettype($a[$v]) != $type) {
                return false;
            }
            $i++;
        }
        return true;
    }
    public static function getRGBInt(string|array $color)
    {
        if (is_array($color) && $this->isList($color, 'integer')) {
            return $color;
        } else if (is_array($color)) {
            return [$color['r'], $color['g'], $color['b']];
        } elseif ($color[0] == '#') {
            $rgb = substr($color, 1, 6);
            $rgblen = strlen($rgb);
            if ($rgblen == 3 || $rgblen == 4) {
                $rgb = "{$rgb[0]}{$rgb[0]}{$rgb[1]}{$rgb[1]}{$rgb[2]}{$rgb[2]}";
            }
            if (strlen($rgb) != 6) {
                throw new Exception('RGB color string error');
            }
            return [hexdec("{$rgb[0]}{$rgb[1]}"), hexdec("{$rgb[2]}{$rgb[3]}"), hexdec("{$rgb[4]}{$rgb[5]}")];
        } elseif ("{$color[0]}{$color[1]}{$color[2]}" == 'rgb') {
            strtok($color, '(');
            return [strtok(', '), strtok(', '), strtok(' /)')];
        } else if (isset(self::COLOR_CSS4[$color])) {
            return self::COLOR_CSS4[$color][0];
        } else {
            throw new InvalidArgumentException("color format not support");
        }
    }

    public function drawGlyph($size, $angle, $x, $y, $color, $font)
    {
        $pos = mt_rand(0, $this->GLYPH_COMPONENT_LEN);
        $text = mb_substr(self::CHAR, $pos, 1);
        $color == self::getRGBInt($color);
        imagefttext($this->img, 10, 0, $x, $y, imagecolorexact($this->img, ...$color), $font, $text);
    }
    private $GLYPH_COMPONENT_LEN;
    //const CHAR = '丨亅丿乛一乙乚丶八勹匕冫卜厂刀刂儿二匚阝丷几卩冂力冖凵人亻入十厶亠匸讠廴又艹屮彳巛川辶寸大飞干工弓廾广彐彑巾口马门宀女犭山彡尸饣士扌氵纟巳土囗兀夕小忄幺弋尢夂子贝比灬长车歹斗厄方风父戈卝户火旡见斤耂毛木肀牛牜爿片攴攵气欠犬日氏礻手殳水瓦尣王韦文毋心牙爻曰月爫支止爪白癶歺甘瓜禾钅立龙矛皿母目疒鸟皮生石矢示罒田玄穴疋业衤用玉耒艸臣虫而耳缶艮虍臼米齐肉色舌覀页先行血羊聿至舟衣竹自羽糸糹貝采镸車辰赤辵豆谷見角克里卤麦身豕辛言邑酉豸走足青靑雨齿長非阜金釒隶門靣飠鱼隹風革骨鬼韭面首韋香頁音髟鬯鬥高鬲馬黄鹵鹿麻麥鳥魚鼎黑黽黍黹鼓鼠幹鼻齊齒龍龠';
    const GLYPH_COMPONENT = '一丨丿丶亅乚乀乙乛𡿨𠃑十二厂丁丂匚七丅丆冂冖刂卜リ人八亻儿勹几匕乂九入丷亠冫讠⺀又厶阝刀凵力卩乃廴了丩土艹大扌工寸干士廾卄三兀尢亏弋亍丌于与才万𠫓口山巾小上亼夂彳犭彡夕亽丸亾凡千饣勺乇及川个久亇宀氵辶忄广亡门之女尸弓子屮幺巛巳彐纟彑己马也刃卂飞乡叉木王戈夫歹犬丰比廿耂井天瓦支开不元友云车旡尤牙五巨屯厷厄无韦帀巿朩日止曰水卝攴中贝少内禸见㓁攵月从爫欠殳毛斤勿手牜牛今氏分爻夭凶公片父厃气戶壬仌丯尣爪长风介化反升卬火心灬方礻户文斗六冘冗爿巴允尹肀丑弔夬毋予丮毌𠬝夨⺗石示戊古可未去甘圥正世玉左卉丙右犮龙朮卌田目罒皿旦冋业且由氺甲占北兄冉央电囚冎歺禾白矢钅生令鸟用瓜句匃夗包乍丘氐卯刍疒穴立衤必玄主龸皮矛出癶母台疋召弗氶耳覀臣共而至戌圭耒吉西页有百亘幵朿寺襾列戍虫吅虍早回同曲此吕光尗肉吋臼舟自缶合竹各多舛舌刖囟先行血兆色夅兇𠂢𠂤𦈢米羊衣屰并次齐交安弚㐫糹羽糸艮聿厽艸弜叒車豆酉豕走束甫辰镸赤夾克麦巠丣貝見里囬足冏肖旲步呂卤肙㒳角身豸釆谷免每余告夆孚辵系兌囪佥言良辛夋雨林者幸其取臥青來或奇坴東戔昔亞長靑靣奄玨夌臤疌直叀門非尚虎果具畀咼卓易齿金隹飠鱼卑兔采阜侖臽兒臾爭𨸏炎炏享放京卒咅宗並隶叕帚彔甾頁革壴面咸柬垚畐昚枼甚垔骨品禺韭曷昜是昷鬼風食重香俞音酋首娄韋飛彖叚馬髟鬲真莫鬥尃盍貢員豈丵秝鬯隻倉舀奚高兼离冡麥票黄堇區專鹵婁鳥魚祭鹿麻啇翏敢參黃厤堯尞萬殼覃黑雈貴買單黹鼎菐番黍喬爲粦曾遂絲鼓爾霝監雚壽歷齒畾黽瞿睘睪喿鼠僉鼻龜龠與會詹龍齊亶襄';

    const COLOR_CSS4 = [
        'aliceblue' => [240, 248, 255],
        'antiquewhite' => [250, 235, 215],
        'aqua' => [0, 255, 255],
        'aquamarine' => [127, 255, 212],
        'azure' => [240, 255, 255],
        'beige' => [245, 245, 220],
        'bisque' => [255, 228, 196],
        'black' => [0, 0, 0],
        'blanchedalmond' => [255, 235, 205],
        'blue' => [0, 0, 255],
        'blueviolet' => [138, 43, 226],
        'brown' => [165, 42, 42],
        'burlywood' => [222, 184, 135],
        'cadetblue' => [95, 158, 160],
        'chartreuse' => [127, 255, 0],
        'chocolate' => [210, 105, 30],
        'coral' => [255, 127, 80],
        'cornflowerblue' => [100, 149, 237],
        'cornsilk' => [255, 248, 220],
        'crimson' => [220, 20, 60],
        'cyan' => [0, 255, 255],
        'darkblue' => [0, 0, 139],
        'darkcyan' => [0, 139, 139],
        'darkgoldenrod' => [184, 134, 11],
        'darkgray' => [169, 169, 169],
        'darkgreen' => [0, 100, 0],
        'darkgrey' => [169, 169, 169],
        'darkkhaki' => [189, 183, 107],
        'darkmagenta' => [139, 0, 139],
        'darkolivegreen' => [85, 107, 47],
        'darkorange' => [255, 140, 0],
        'darkorchid' => [153, 50, 204],
        'darkred' => [139, 0, 0],
        'darksalmon' => [233, 150, 122],
        'darkseagreen' => [143, 188, 143],
        'darkslateblue' => [72, 61, 139],
        'darkslategray' => [47, 79, 79],
        'darkslategrey' => [47, 79, 79],
        'darkturquoise' => [0, 206, 209],
        'darkviolet' => [148, 0, 211],
        'deeppink' => [255, 20, 147],
        'deepskyblue' => [0, 191, 255],
        'dimgray' => [105, 105, 105],
        'dimgrey' => [105, 105, 105],
        'dodgerblue' => [30, 144, 255],
        'firebrick' => [178, 34, 34],
        'floralwhite' => [255, 250, 240],
        'forestgreen' => [34, 139, 34],
        'fuchsia' => [255, 0, 255],
        'gainsboro' => [220, 220, 220],
        'ghostwhite' => [248, 248, 255],
        'gold' => [255, 215, 0],
        'goldenrod' => [218, 165, 32],
        'gray' => [128, 128, 128],
        'green' => [0, 128, 0],
        'greenyellow' => [173, 255, 47],
        'grey' => [128, 128, 128],
        'honeydew' => [240, 255, 240],
        'hotpink' => [255, 105, 180],
        'indianred' => [205, 92, 92],
        'indigo' => [75, 0, 130],
        'ivory' => [255, 255, 240],
        'khaki' => [240, 230, 140],
        'lavender' => [230, 230, 250],
        'lavenderblush' => [255, 240, 245],
        'lawngreen' => [124, 252, 0],
        'lemonchiffon' => [255, 250, 205],
        'lightblue' => [173, 216, 230],
        'lightcoral' => [240, 128, 128],
        'lightcyan' => [224, 255, 255],
        'lightgoldenrodyellow' => [250, 250, 210],
        'lightgray' => [211, 211, 211],
        'lightgreen' => [144, 238, 144],
        'lightgrey' => [211, 211, 211],
        'lightpink' => [255, 182, 193],
        'lightsalmon' => [255, 160, 122],
        'lightseagreen' => [32, 178, 170],
        'lightskyblue' => [135, 206, 250],
        'lightslategray' => [119, 136, 153],
        'lightslategrey' => [119, 136, 153],
        'lightsteelblue' => [176, 196, 222],
        'lightyellow' => [255, 255, 224],
        'lime' => [0, 255, 0],
        'limegreen' => [50, 205, 50],
        'linen' => [250, 240, 230],
        'magenta' => [255, 0, 255],
        'maroon' => [128, 0, 0],
        'mediumaquamarine' => [102, 205, 170],
        'mediumblue' => [0, 0, 205],
        'mediumorchid' => [186, 85, 211],
        'mediumpurple' => [147, 112, 219],
        'mediumseagreen' => [60, 179, 113],
        'mediumslateblue' => [123, 104, 238],
        'mediumspringgreen' => [0, 250, 154],
        'mediumturquoise' => [72, 209, 204],
        'mediumvioletred' => [199, 21, 133],
        'midnightblue' => [25, 25, 112],
        'mintcream' => [245, 255, 250],
        'mistyrose' => [255, 228, 225],
        'moccasin' => [255, 228, 181],
        'navajowhite' => [255, 222, 173],
        'navy' => [0, 0, 128],
        'oldlace' => [253, 245, 230],
        'olive' => [128, 128, 0],
        'olivedrab' => [107, 142, 35],
        'orange' => [255, 165, 0],
        'orangered' => [255, 69, 0],
        'orchid' => [218, 112, 214],
        'palegoldenrod' => [238, 232, 170],
        'palegreen' => [152, 251, 152],
        'paleturquoise' => [175, 238, 238],
        'palevioletred' => [219, 112, 147],
        'papayawhip' => [255, 239, 213],
        'peachpuff' => [255, 218, 185],
        'peru' => [205, 133, 63],
        'pink' => [255, 192, 203],
        'plum' => [221, 160, 221],
        'powderblue' => [176, 224, 230],
        'purple' => [128, 0, 128],
        'rebeccapurple' => [102, 51, 153],
        'red' => [255, 0, 0],
        'rosybrown' => [188, 143, 143],
        'royalblue' => [65, 105, 225],
        'saddlebrown' => [139, 69, 19],
        'salmon' => [250, 128, 114],
        'sandybrown' => [244, 164, 96],
        'seagreen' => [46, 139, 87],
        'seashell' => [255, 245, 238],
        'sienna' => [160, 82, 45],
        'silver' => [192, 192, 192],
        'skyblue' => [135, 206, 235],
        'slateblue' => [106, 90, 205],
        'slategray' => [112, 128, 144],
        'slategrey' => [112, 128, 144],
        'snow' => [255, 250, 250],
        'springgreen' => [0, 255, 127],
        'steelblue' => [70, 130, 180],
        'tan' => [210, 180, 140],
        'teal' => [0, 128, 128],
        'thistle' => [216, 191, 216],
        'tomato' => [255, 99, 71],
        'turquoise' => [64, 224, 208],
        'violet' => [238, 130, 238],
        'wheat' => [245, 222, 179],
        'white' => [255, 255, 255],
        'whitesmoke' => [245, 245, 245],
        'yellow' => [255, 255, 0],
        'yellowgreen' => [154, 205, 50],
    ];
}
