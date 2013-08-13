<?php
/**
 * This file contains all logic
 */


function showRecentTexts()
{
    $db = getDb();
    $sth = $db->prepare('SELECT wt.id, it.content, wt.created from wt_text_block wt left join wt_indexed_string it on  (it.id = wt.name_id) order by wt.created desc limit 20');
    $sth->execute();


    $records = $sth->fetchAll(PDO::FETCH_ASSOC);

    $answer = array("type"=>"recent", "records"=>$records );

    //good place to call some logger.
    echo json_encode($answer);
}

function uploadNewText()
{
    global $frequencyLimit;

    $name = mb_strtolower($_POST["name"], 'UTF-8');
    $text = $_POST["text"];

    $nameIndexed = getHashFromContents(getOrCreateIndexedTextIds(array(0=>$name)));
    $nameId = $nameIndexed[$name];

    $db = getDb();
    $stmt = $db->prepare("INSERT INTO wt_text_block (name_id) VALUES(?)");
    $stmt->execute(array($nameId));

    $textId = $db->lastInsertId();

    $normalizedText = splitByPairs($text);
    $textesIndexed = getHashFromContents(getOrCreateIndexedTextIds($normalizedText));
    $stmt = $db->prepare("INSERT INTO wt_text_chunk (text_block_id, order_number, content_id, leftover_content_id) VALUES(?,?,?,?)");
    for($i = 0; $i < sizeof($normalizedText); $i++)
    {
        $word = $normalizedText[$i];
        $separator = $normalizedText[$i+1];

        $stmt->execute(array($textId, (int) $i/2, $textesIndexed[$word], $textesIndexed[$separator]));

        //one more increment
        $i++;
    }

    $weights = analyzeText($textId);
    $stmt = $db->prepare("INSERT INTO wt_chunk_in_block_frequency_record (content_id1, content_id2, text_block_id, frequency) VALUES(?,?,?,?)");
    foreach($weights as $comb=>$weight)
    {
        $ids = explode("_", $comb);
        $stmt->execute(array((int) $ids[0], (int) $ids[1], $textId, $weight));
    }

    //mark top combinations. update with limit are not SQL standard, so we made it with subquery.
    //mysql doesn't support subqueries limits overall, so two requests.

    $stmt = $db->prepare("select id from wt_chunk_in_block_frequency_record where text_block_id = ? order by frequency desc limit " .$frequencyLimit);
    $stmt->execute(array($textId));
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtUpdate = $db->prepare("update wt_chunk_in_block_frequency_record set is_top = 1 where id  = ?");
    foreach($records as $r)
    {
        $stmtUpdate->execute(array($r["id"]));
    }

    $answer = array("type"=>"uploaddone", "id"=>$textId);

    echo json_encode($answer);
}

function analyzeText($textId)
{
    $db = getDb();
    $sth = $db->prepare('SELECT tc.content_id as c from wt_text_chunk tc where tc.text_block_id = ? order by tc.order_number');
    $sth->execute(array($textId));

    $records = $sth->fetchAll(PDO::FETCH_ASSOC);

    $weights = array();
    for ($i = 0; $i < sizeof($records) - 2; $i++)
    {
        $r0 = $records[$i];
        $r1 = $records[$i + 1];
        $r2 = $records[$i + 2];

        $comb1 = $r0["c"]."_".$r1["c"];
        $comb2 = $r0["c"]."_".$r2["c"];
        if(!isset($weights[$comb1]))
        {
            $weights[$comb1] = 0;
        }
        if(!isset($weights[$comb2]))
        {
            $weights[$comb2] = 0;
        }

        $weights[$comb1] += 2;
        $weights[$comb2] += 1;
    }

    return $weights;
}

function showTextInfo()
{
    global $frequencyLimit;

    $textId = (int) $_POST["id"];
    $db = getDb();
    $sth = $db->prepare('SELECT cont.content as c1, sep.content as c2 from wt_text_chunk tc left join wt_indexed_string cont on (cont.id = tc.content_id)  left join wt_indexed_string sep on (sep.id = tc.leftover_content_id) where tc.text_block_id = ? order by tc.order_number');
    $sth->execute(array($textId));


    $restoredText = "";
    $records = $sth->fetchAll(PDO::FETCH_ASSOC);
    foreach($records as $r)
    {
        $restoredText .= $r["c1"].$r["c2"];
    }


    $sth = $db->prepare('SELECT tf.content_id1 as c1, tf.content_id2 as c2, c1.content as a, c2.content as b, tf.frequency as f from wt_chunk_in_block_frequency_record tf left join wt_indexed_string c1 on (c1.id = tf.content_id1) left join wt_indexed_string c2 on (c2.id = tf.content_id2) where tf.text_block_id = ? and tf.is_top = 1 order by tf.frequency desc limit '. $frequencyLimit);
    $sth->execute(array($textId)) or print_r ($sth->errorInfo());

    $freq_info = $sth->fetchAll(PDO::FETCH_ASSOC);

    $sqlSimilar = "select wf.text_block_id as id, it.content, wt.created, count(*) as cnt, sum(wf.frequency) as freq from wt_chunk_in_block_frequency_record wf left join wt_text_block wt on (wt.id = wf.text_block_id) left join wt_indexed_string it on  (it.id = wt.name_id) where wf.is_top = 1 and wf.text_block_id != ? and ( FALSE ";
    $params = array();
    $params[] = $textId;
    foreach($freq_info as $r)
    {
        $sqlSimilar .= " or (wf.content_id1 = ? and wf.content_id2 = ?)";
        $params[] = $r["c1"];
        $params[] = $r["c2"];
    }

    $sqlSimilar .= ") group by wf.text_block_id, it.content, wt.created order by count(*) desc limit 5";
    $sth = $db->prepare($sqlSimilar);
    $sth->execute($params) or print_r ($sth->errorInfo());

    $similar_info = $sth->fetchAll(PDO::FETCH_ASSOC);


    $answer = array("type"=>"text", "text"=>$restoredText, "freq_info" => $freq_info, "similar" => $similar_info);

    echo json_encode($answer);
}

/**
 * Helper methods
 * todo - optimize WORD detection, could be implement by char ranges
 */

$globalLetters = array_merge(range('a', 'z'), range('A', 'Z'), preg_split('/(?<!^)(?!$)/u', "ёйцукенгшщзхъфывапролджэячсмитьбюЁЙЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЯЧСМИТЬБЮ0123456789" ));

function splitByPairs($string)
{
    global $globalLetters;

    $ret = array();
    $x = preg_split('/(?<!^)(?!$)/u', $string );

    $STATE_NONE = 0;
    $STATE_IN_WORD = 1;
    $STATE_IN_SEPARATOR = 2;

    $state = $STATE_NONE;
    $currentWord = "";
    $currentSeparator = "";
    //simple state machine parsing
    for($i =0; $i < sizeof($x); $i++)
    {
        //
        $symbol = $x[$i];
        $currentSymbolIsWord = in_array($symbol, $globalLetters);
        if($currentSymbolIsWord)
        {
            if($state == $STATE_IN_SEPARATOR)
            {
                $ret[] = mb_strtolower($currentWord, 'UTF-8');
                $ret[] = mb_strtolower($currentSeparator, 'UTF-8');

                $currentWord = "";
                $currentSeparator = "";
                $currentWord .= $symbol;
                $state = $STATE_IN_WORD;
            }
            else  if($state == $STATE_IN_WORD)
            {
                $currentWord .= $symbol;
            }
            else if($state == $STATE_NONE)
            {
                $state = $STATE_IN_WORD;
                $currentWord .= $symbol;
            }
        }
        else
        {
            if($state == $STATE_IN_SEPARATOR)
            {
                $currentSeparator .= $symbol;
            }
            else if($state == $STATE_IN_WORD)
            {
                $state = $STATE_IN_SEPARATOR;
                $currentSeparator .= $symbol;
            }
            else if($state == $STATE_NONE)
            {
                $state = $STATE_IN_SEPARATOR;
                $currentWord = "";
                $currentSeparator .= $symbol;
            }
        }
    }
    if($state == $STATE_IN_SEPARATOR || $state == $STATE_IN_WORD)
    {
        $ret[] = mb_strtolower($currentWord, 'UTF-8');
        $ret[] = mb_strtolower($currentSeparator, 'UTF-8');
    }
    return $ret;
}

/**
 * @param $arr of strings
 * Optimized way of getting ids from DB indexed table
 *
 * @return array with both content and ids, NOT a hash.
 */
function getOrCreateIndexedTextIds($arr)
{
    $arr = array_values(array_unique($arr));
    if(sizeof($arr) == 0 )
    {
        return array();
    }

    $db = getDb();
    $inQuery = implode(',', array_fill(0, count($arr), '?'));
    $qw = 'SELECT it.id, it.content from wt_indexed_string it where it.content in ('.  $inQuery . ' )';
    $sth = $db->prepare($qw);
    foreach ($arr as $k => $id)
        $sth->bindValue(($k+1), $id, PDO::PARAM_STR);

    $sth->execute() or die ($sth->errorInfo());
    $records = $sth->fetchAll(PDO::FETCH_ASSOC);
    $stringsKnown = array();

    foreach($records as $r)
    {
        $stringsKnown[] = $r["content"];
    }

    $stringUnknown = array_values(array_unique(array_diff($arr, $stringsKnown)));
    if(sizeof($stringUnknown) > 0 )
    {
        //some unknown words have we here
        //database to insert need we

        $qw = 'insert into wt_indexed_string (content) values (?) ';
        $sth = $db->prepare($qw);
        foreach ($stringUnknown as $k => $id)
            $sth->execute(array($id));

        //after insert, let's get them ids out;
        //this can be done in one query, it was made one by one to avoid mysql bug with unique constraint
        // which treats '.' and '. ' as same. Doh.
        //no recursion since dumb mysql bug!!!
        $inQuery = implode(',', array_fill(0, count($stringUnknown), '?'));
        $qw = 'SELECT it.id, it.content from wt_indexed_string it where it.content in ('.  $inQuery . ' )';
        $sth = $db->prepare($qw);
        foreach ($stringUnknown as $k => $id)
            $sth->bindValue(($k+1), $id, PDO::PARAM_STR);

        $sth->execute() or die ($sth->errorInfo());
        $recordsMore = $sth->fetchAll(PDO::FETCH_ASSOC);

        $records = array_merge($records, $recordsMore);
    }

    return $records;
}


function getHashFromContents($arr)
{
    $result = array();

    foreach($arr as $r)
    {
        $result[$r["content"]] = $r["id"];
    }

    return $result;
}



function bindArrayValue($req, $array, $typeArray = false)
{
    if(is_object($req) && ($req instanceof PDOStatement))
    {
        foreach($array as $key => $value)
        {
            if($typeArray)
                $req->bindValue(":$key",$value,$typeArray[$key]);
            else
            {
                if(is_int($value))
                    $param = PDO::PARAM_INT;
                elseif(is_bool($value))
                    $param = PDO::PARAM_BOOL;
                elseif(is_null($value))
                    $param = PDO::PARAM_NULL;
                elseif(is_string($value))
                    $param = PDO::PARAM_STR;
                else
                    $param = FALSE;

                if($param)
                    $req->bindValue(":$key",$value,$param);
            }
        }
    }
}
