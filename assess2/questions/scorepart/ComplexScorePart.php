<?php

namespace IMathAS\assess2\questions\scorepart;

require_once(__DIR__ . '/ScorePart.php');

use IMathAS\assess2\questions\models\ScoreQuestionParams;

class ComplexScorePart implements ScorePart
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
        if (isset($options['answerformat'])) {if (is_array($options['answerformat'])) {$answerformat = $options['answerformat'][$partnum];} else {$answerformat = $options['answerformat'];}}
        if (isset($options['requiretimes'])) {if (is_array($options['requiretimes'])) {$requiretimes = $options['requiretimes'][$partnum];} else {$requiretimes = $options['requiretimes'];}}
        if (isset($options['requiretimeslistpart'])) {if (is_array($options['requiretimeslistpart'])) {$requiretimeslistpart = $options['requiretimeslistpart'][$partnum];} else {$requiretimeslistpart = $options['requiretimeslistpart'];}}
        if (isset($options['ansprompt'])) {if (is_array($options['ansprompt'])) {$ansprompt = $options['ansprompt'][$partnum];} else {$ansprompt = $options['ansprompt'];}}

        if (!isset($reltolerance) && !isset($abstolerance)) { $reltolerance = $defaultreltol;}
        if ($multi) { $qn = ($qn+1)*1000+$partnum; }
        if (!isset($answerformat)) { $answerformat = '';}
        $ansformats = array_map('trim',explode(',',$answerformat));

        if (in_array('nosoln',$ansformats) || in_array('nosolninf',$ansformats)) {
            list($givenans, $_POST["tc$qn"], $answer) = scorenosolninf($qn, $givenans, $answer, $ansprompt);
        }
        $givenans = normalizemathunicode($givenans);
        $GLOBALS['partlastanswer'] = $givenans;
        if ($anstype=='calccomplex' && $hasNumVal) {
            $GLOBALS['partlastanswer'] .= '$#$' . $givenansval;
        }
        if ($givenans == null) {return 0;}
        $answer = str_replace(' ','',makepretty($answer));
        $givenans = trim($givenans);

        if ($answer=='DNE') {
            if (strtoupper($givenans)=='DNE') {
                return 1;
            } else {
                return 0;
            }
        } else if ($answer=='oo') {
            if ($givenans=='oo') {
                return 1;
            } else {
                return 0;
            }
        }

        $gaarr = array_map('trim',explode(',',$givenans));

        if ($anstype=='calccomplex') {
            //test for correct format, if specified
            if (($answer!='DNE'&&$answer!='oo') && checkreqtimes($givenans,$requiretimes)==0) {
                return 0;
            }
            foreach ($gaarr as $i=>$tchk) {
                if (in_array('sloppycomplex',$ansformats)) {
                    $tchk = str_replace(array('sin','pi'),array('s$n','p$'),$tchk);
                    if (substr_count($tchk,'i')>1) {
                        return 0;
                    }
                    $tchk = str_replace(array('s$n','p$'),array('sin','pi'),$tchk);
                } else {
                    // TODO: rewrite using mathparser
                    $cpts = parsecomplex($tchk);

                    if (!is_array($cpts)) {
                        return 0;
                    }
                    $cpts[1] = ltrim($cpts[1], '+');
                    $cpts[1] = rtrim($cpts[1], '*');

                    //echo $cpts[0].','.$cpts[1].'<br/>';
                    if ($answer!='DNE'&&$answer!='oo' && (!checkanswerformat($cpts[0],$ansformats) || !checkanswerformat($cpts[1],$ansformats))) {
                        //return 0;
                        unset($gaarr[$i]);
                    }
                    if ($answer!='DNE'&&$answer!='oo' && isset($requiretimeslistpart) && checkreqtimes($tchk,$requiretimeslistpart)==0) {
                        //return 0;
                        unset($gaarr[$i]);
                    }
                }
            }
        }

        $ganumarr = array();
        foreach ($gaarr as $j=>$givenans) {
            $gaparts = parsesloppycomplex($givenans);
            if (!in_array('exactlist',$ansformats)) {
                // don't add if we already have it in the list
                foreach ($ganumarr as $prevvals) {
                    if (abs($gaparts[0]-$prevvals[0])<1e-12 && abs($gaparts[1]-$prevvals[1])<1e-12) {
                        continue 2; //skip adding it to the list
                    }
                }
            }
            $ganumarr[] = $gaparts;
        }

        $anarr = array_map('trim',explode(',',$answer));
        $annumarr = array();
        foreach ($anarr as $i=>$answer) {
            $ansparts = parsesloppycomplex($answer);
            if (!in_array('exactlist',$ansformats)) {
                foreach ($annumarr as $prevvals) {
                    if (abs($ansparts[0]-$prevvals[0])<1e-12 && abs($ansparts[1]-$prevvals[1])<1e-12) {
                        continue 2; //skip adding it to the list
                    }
                }
            }
            $annumarr[] = $ansparts;
        }

        if (count($ganumarr)==0) {
            return 0;
        }
        $extrapennum = count($ganumarr)+count($annumarr);
        $correct = 0;
        foreach ($annumarr as $i=>$ansparts) {
            $foundloc = -1;

            foreach ($ganumarr as $j=>$gaparts) {
                if (count($ansparts)!=count($gaparts)) {
                    break;
                }
                for ($i=0; $i<count($ansparts); $i++) {
                    if (is_numeric($ansparts[$i]) && is_numeric($gaparts[$i])) {
                        if (isset($abstolerance)) {
                            if (abs($ansparts[$i]-$gaparts[$i]) >= $abstolerance + 1E-12) {break;}
                        } else {
                            if (abs($ansparts[$i]-$gaparts[$i])/(abs($ansparts[$i])+.0001) >= $reltolerance+ 1E-12) {break;}
                        }
                    }
                }
                if ($i==count($ansparts)) {
                    $correct += 1; $foundloc = $j; break;
                }
            }
            if ($foundloc>-1) {
                array_splice($ganumarr,$foundloc,1); // remove from list
                if (count($ganumarr)==0) {
                    break;
                }
            }
        }
        $score = $correct/count($annumarr) - count($ganumarr)/$extrapennum;
        if ($score<0) { $score = 0; }
        return ($score);
    }
}
