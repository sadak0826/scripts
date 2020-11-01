<?php

function create_method_call($function, $info)
{
    $contents = ''."\n";
    $contents .= '        // 試験'."\n";
    if($info['is_public'] == 1)
    {
        $contents .= '        $actual = $test_class->'.$function.';'."\n";
    }
    else
    {
        $contents .= '        $reflection = new \ReflectionClass($test_class);'."\n";
        $contents .= '        $method = $reflection->getMethod('.$info['name'].');'."\n";
        $contents .= '        $method->setAccessible(true);'."\n";
        $contents .= '        $actual = $method->invokeArgs($test_class, array('.$info['param'].'));'."\n";
    }
    return $contents;
}

// 1. テスト対象クラスの解析
$data = array();
$data['class'] = null;
$data['function'] = array();
$line_count = 0;
$filepath = $argv[1];
$file = fopen($filepath, "r");
if($file)
{
    while ($line = fgets($file))
    {
        $line_count++;

        // class
        if(preg_match('/class/', $line))
        {
            // class以降の文字列を切り出す
            $pieces1 = explode("class ", $line);

            // extends を想定して半角スペースで分割
            $pieces2 = explode(' ', $pieces1[1]);
            $data['class'] = $pieces2[0];
        }

        // function
        if(preg_match('/function /', $line))
        {
            $pieces = explode("function ", $line);
            $function = $pieces[1];

            // 改行の除去
            $function = str_replace("\n", '', $function);

            // ブレース({})の除去
            $function = str_replace('{', '', $function);
            $function = str_replace('}', '', $function);

            // 半角スペースの除去
            $function = str_replace(' ', '', $function);

            // 初期値を除去($x=trueなど)
            if(preg_match_all('/=(\w+)/', $function, $matches))
            {
                foreach($matches[0] as $index => $value)
                {
                    $function = str_replace($value, '', $function);
                }
            }

            // カンマ(,)の後ろに半角スペースを追加
            $function = str_replace(',', ', ', $function);

            // 引数部分を除いたfunction名を抽出
            preg_match_all('/(\w+)\(/', $function, $matches1);

            // 引数部分をを抽出
            preg_match_all('/\$(\w+)/', $function, $matches2);

            $data['function'][$function]['param'] = implode(', ',$matches2[0]);
            $data['function'][$function]['name'] = $matches1[1][0];
            $data['function'][$function]['line'] = $line_count;
            $data['function'][$function]['return'] = array();
            $data['function'][$function]['throw'] = array();

            // public であるか？
            if(preg_match('/public /', $line))
            {
                $data['function'][$function]['is_public'] = 1;
            }
            else
            {
                $data['function'][$function]['is_public'] = 0;
            }
        }

        // return
        if(preg_match('/return/', $line))
        {
            $data['function'][$function]['return'][] = $line_count;
        }

        // throw
        if(preg_match('/throw/', $line))
        {
            $data['function'][$function]['throw'][] = $line_count;
        }
    }
}

fclose($file);
//print_r($data);

// 2. テストスケルトンの生成と出力
$export_filepath = './'.$data['class'].'Test.php';
$contents = null;
$contents .= '<?php'."\n";
$contents .= ''."\n";
$contents .= '// TODO ファイルパスを確認'."\n";
$contents .= "require_once(SOURCE_PATH.'/{$filepath}');"."\n";
$contents .= ''."\n";
$contents .= "class {$data['class']}Test extends PHPUnit_Framework_TestCase"."\n";
$contents .= '{'."\n";

foreach($data['function'] as $function => $info)
{
    // テスト分岐が１つしかないか判定
    // returnなし or throwなし or returnが１つ
    if(empty($info['return']) && empty($info['throw']))
    {
        // データプロバイダなし
        preg_match('/(\w+)\(/', $function, $matches1);
        preg_match_all('/\$(\w+)/', $function, $matches2);
        $contents .= '    /**'."\n";
        $contents .= '     * '.$matches1[1]."のテスト.\n";
        $contents .= '     *'."\n";
        $contents .= '     */'."\n";
        $contents .= '    public function test_'.$matches1[1].'()'."\n";
        $contents .= '    {'."\n";
        $contents .= '        $test_class = new '.$data['class'].'();'."\n";
        $contents .= ''."\n";
        $contents .= '        // 準備'."\n";
        foreach($matches2[0] as $val)
        {
            $contents .= '        '.$val.' = null;'."\n";
        }
        $contents .= create_method_call($function, $info);
        $contents .= ''."\n";
        $contents .= '        // 検証'."\n";
        $contents .= '        $this->assertEquals($expected, $actual);'."\n";
        $contents .= '    }'."\n";
        $contents .= ''."\n";
    }
    else
    {
        // データプロバイダあり
        preg_match('/(\w+)\(/', $function, $matches1);
        preg_match_all('/\$(\w+)/', $function, $matches2);
        $provider_function_name = str_replace(')', ', $mock, $expected)', $function);
        $contents .= '    /**'."\n";
        $contents .= '     * '.$matches1[1]."のテスト.\n";
        $contents .= '     * @dataProvider provider_'.$matches1[1]."\n";
        $contents .= '     */'."\n";
        $contents .= '    public function test_'.$provider_function_name."\n";
        $contents .= '    {'."\n";
        $contents .= ''."\n";
        $contents .= '        $test_class = new '.$data['class'].'();'."\n";
        $contents .= ''."\n";
        $contents .= '        // 準備'."\n";
        $contents .= '        $mock_class = $this->getMockBuilder($mock[\'class\'][\'name\'])'."\n";
        $contents .= '                           ->setMethods(array_column(array_keys($mock[\'class\'][\'method\'])))'."\n";
        $contents .= '                           ->getMock();'."\n";
        $contents .= ''."\n";
        $contents .= '        foreach($mock[\'class\'][\'method\'] as $method_name => return_value)'."\n";
        $contents .= '        {'."\n";
        $contents .= '            $mock_class->method($method_name)->willReturn($return_value1);'."\n";
        $contents .= '        }'."\n";
        $contents .= ''."\n";
        $contents .= '        // TODO モックオブジェクトをテストクラスにインジェクション'."\n";
        $contents .= create_method_call($function, $info);
        $contents .= ''."\n";
        $contents .= '        // 検証'."\n";
        $contents .= '        $this->assertEquals($expected, $actual);'."\n";
        $contents .= '    }'."\n";
        $contents .= ''."\n";
        $contents .= '    /**'."\n";
        $contents .= '     * '.$matches1[1]."のデータプロバイダ.\n";
        $contents .= '     */'."\n";
        $contents .= '    public function provider_'.$matches1[1]."\n";
        $contents .= '    {'."\n";
        $contents .= '        return array('."\n";
        for($i = 1; $i < (count($info['return']) + count($info['throw'])) + 1; $i++)
        {
            $contents .= "            'case{$i} XXX' => array("."\n";
            foreach($matches2[1] as $val)
            {
                $contents .= "                '', // {$val}"."\n";
            }
            $contents .= "                '', // expected"."\n";
            $contents .= "                array( // mock"."\n";
            $contents .= "                   'class' => array("."\n";
            $contents .= "                       'name' => 'YYY',"."\n";
            $contents .= "                       'method' => array("."\n";
            $contents .= "                           'mathod_name1' => 'retuen_value1',"."\n";
            $contents .= "                           'mathod_name2' => 'retuen_value2',"."\n";
            $contents .= "                       ),"."\n";
            $contents .= "                   ),"."\n";
            $contents .= "                ),"."\n";
            $contents .= "            ),"."\n";
        }
        $contents .= '        );'."\n";
        $contents .= '    }'."\n";
        $contents .= ''."\n";
    }
}

$contents .= '}'."\n";

//print_r($contents);
file_put_contents($export_filepath , $contents);
