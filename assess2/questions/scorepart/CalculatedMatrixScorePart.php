<?php

namespace IMathAS\assess2\questions\scorepart;

require_once(__DIR__ . '/ScorePart.php');

use IMathAS\assess2\questions\models\ScoreQuestionParams;

class CalculatedMatrixScorePart implements ScorePart
{
    private $scoreQuestionParams;

    public function __construct(ScoreQuestionParams $scoreQuestionParams)
    {
        $this->scoreQuestionParams = $scoreQuestionParams;
    }

    public function getScore(): int
    {
        global $mathfuncs;

        $RND = $this->scoreQuestionParams->getRandWrapper();
        $options = $this->scoreQuestionParams->getVarsForScorePart();
        $qn = $this->scoreQuestionParams->getQuestionNumber();
        $givenans = $this->scoreQuestionParams->getGivenAnswer();
        $multi = $this->scoreQuestionParams->getIsMultiPartQuestion();
        $partnum = $this->scoreQuestionParams->getQuestionPartNumber();

        $defaultreltol = .0015;

        if (is_array($options['answer'])) {$answer = $options['answer'][$partnum];} else {$answer = $options['answer'];}
        if (isset($options['reltolerance'])) {if (is_array($options['reltolerance'])) {$reltolerance = $options['reltolerance'][$partnum];} else {$reltolerance = $options['reltolerance'];}}
        if (isset($options['abstolerance'])) {if (is_array($options['abstolerance'])) {$abstolerance = $options['abstolerance'][$partnum];} else {$abstolerance = $options['abstolerance'];}}
        if (!isset($reltolerance) && !isset($abstolerance)) { $reltolerance = $defaultreltol;}
        if (isset($options['answersize'])) {if (is_array($options['answersize'])) {$answersize = $options['answersize'][$partnum];} else {$answersize = $options['answersize'];}}
        if (isset($options['answerformat'])) {if (is_array($options['answerformat'])) {$answerformat = $options['answerformat'][$partnum];} else {$answerformat = $options['answerformat'];}}

        if ($multi) { $qn = ($qn+1)*1000+$partnum; }
        if (!isset($answerformat)) { $answerformat = '';}
        $ansformats = array_map('trim',explode(',',$answerformat));

        if (in_array('nosoln',$ansformats) || in_array('nosolninf',$ansformats)) {
            list($givenans, $_POST["tc$qn"], $answer) = scorenosolninf($qn, $givenans, $answer, $ansprompt);
        }
        //store answers
        if ($givenans==='oo' || $givenans==='DNE') {
            $GLOBALS['partlastanswer'] = $givenans;
        } else if (isset($answersize)) {
            $sizeparts = explode(',',$answersize);
            $givenanslist = array();
            if ($hasNumVal) {
                $givenanslistvals = explode('|', $givenansval);
            } else {
                $givenanslistvals = array();
            }
            for ($i=0; $i<$sizeparts[0]*$sizeparts[1]; $i++) {
                $givenanslist[$i] = $_POST["qn$qn-$i"];
                if (!$hasNumVal) {
                    $givenanslistvals[$i] = evalMathParser($_POST["qn$qn-$i"]);
                }
            }
            $GLOBALS['partlastanswer'] = implode("|",$givenanslist);
            $GLOBALS['partlastanswer'] .= '$#$'.implode('|', $givenanslistvals);
        } else {
            $givenans = preg_replace('/\)\s*,\s*\(/','),(', $givenans);
            $givenanslist = explode(',', str_replace('),(', ',', substr($givenans,2,-2)));
            if ($hasNumVal) {
                $givenanslistvals = explode('|', $givenansval);
            } else {
                foreach ($givenanslist as $j=>$v) {
                    $givenanslistvals[$j] = evalMathParser($v);
                }
            }
            //this may not be backwards compatible
            $GLOBALS['partlastanswer'] = $givenans.'$#$'.implode('|', $givenanslistvals);
        }

        //handle nosolninf case
        if ($givenans==='oo' || $givenans==='DNE') {
            if ($answer==$givenans) {
                return 1;
            } else {
                return 0;
            }
        } else if ($answer==='DNE' || $answer==='oo') {
            return 0;
        }

        $correct = true;

        $ansr = substr($answer,2,-2);
        $ansr = preg_replace('/\)\s*\,\s*\(/',',',$ansr);
        $answerlist = explode(',',$ansr);

        foreach ($answerlist as $k=>$v) {
            $answerlist[$k] = evalMathParser($v);
        }
        if (isset($answersize)) {
            for ($i=0; $i<count($answerlist); $i++) {
                if (!checkanswerformat($givenanslist[$i],$ansformats)) {
                    return 0; //perhaps should just elim bad answer rather than all?
                }
            }

        } else {
            if (substr_count($answer,'),(')!=substr_count($givenans,'),(')) {
                $correct = false;
            }
            $tocheck = str_replace(' ','', $givenans);
            $tocheck = str_replace(array('],[','),(','>,<'),',',$tocheck);
            $tocheck = substr($tocheck,2,-2);
            $tocheck = explode(',',$tocheck);
            foreach($tocheck as $chkme) {
                if (!checkanswerformat($chkme,$ansformats)) {
                    return 0; //perhaps should just elim bad answer rather than all?
                }
            }
        }

        if (count($answerlist) != count($givenanslist)) {
            return 0;
        }

        if (in_array('scalarmult',$ansformats)) {
            //scale givenanslist to the magnitude of $answerlist
            $mag = sqrt(array_sum(array_map(function($x) {return $x*$x;}, $answerlist)));
            $mag2 = sqrt(array_sum(array_map(function($x) {return $x*$x;}, $givenanslistvals)));
            if ($mag > 0 && $mag2 > 0) {
                foreach ($answerlist as $j=>$v) {
                    if (abs($v)>1e-10) {
                        if ($answerlist[$j]*$givenanslistvals[$j]<0) {
                            $mag *= -1;
                        }
                        break;
                    }
                }
                foreach ($givenanslistvals as $j=>$v) {
                    $givenanslistvals[$j] = $mag/$mag2*$v;
                }
            }
        }

        for ($i=0; $i<count($answerlist); $i++) {
            if (isset($abstolerance)) {
                if (abs($answerlist[$i] - $givenanslistvals[$i]) > $abstolerance-1E-12) {
                    $correct = false;
                    break;
                }
            } else {
                if (abs($answerlist[$i] - $givenanslistvals[$i])/(abs($answerlist[$i])+.0001) > $reltolerance-1E-12) {
                    $correct = false;
                    break;
                }
            }
        }
        if ($correct) {return 1;} else {return 0;}
    }
}
