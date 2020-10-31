<?php

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
            $data['function'][$function]['line'] = $line_count;
            $data['function'][$function]['return'] = array();
            $data['function'][$function]['throw'] = array();
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
        foreach($matches2[0] as $val)
        {
            $contents .= '        '.$val.' = null;'."\n";
        }
        $contents .= '        $actual = $test_class->'.$function.';'."\n";
        $contents .= '        $this->assertEquals($expected, $actual);'."\n";
        $contents .= '    }'."\n";
        $contents .= ''."\n";
    }
    else
    {
        // データプロバイダあり
        preg_match('/(\w+)\(/', $function, $matches1);
        preg_match_all('/\$(\w+)/', $function, $matches2);
        $provider_function_name = str_replace(')', ', $expected)', $function);
        $contents .= '    /**'."\n";
        $contents .= '     * '.$matches1[1]."のテスト.\n";
        $contents .= '     * @dataProvider provider_'.$matches1[1]."\n";
        $contents .= '     */'."\n";
        $contents .= '    public function test_'.$provider_function_name."\n";
        $contents .= '    {'."\n";
        $contents .= '        $test_class = new '.$data['class'].'();'."\n";
        $contents .= '        $actual = $test_class->'.$function.';'."\n";
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
