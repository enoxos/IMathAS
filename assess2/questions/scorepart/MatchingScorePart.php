<?php

namespace IMathAS\assess2\questions\scorepart;

require_once(__DIR__ . '/ScorePart.php');

use IMathAS\assess2\questions\models\ScoreQuestionParams;

class MatchingScorePart implements ScorePart
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

        if (is_array($options['questions'][$partnum])) {$questions = $options['questions'][$partnum];} else {$questions = $options['questions'];}
        if (isset($options['answers'])) {if (is_array($options['answers'][$partnum])) {$answers = $options['answers'][$partnum];} else {$answers = $options['answers'];}}
        else if (isset($options['answer'])) {if (is_array($options['answer'][$partnum])) {$answers = $options['answer'][$partnum];} else {$answers = $options['answer'];}}
        if (is_array($options['matchlist'])) {$matchlist = $options['matchlist'][$partnum];} else {$matchlist = $options['matchlist'];}
        if (isset($options['noshuffle'])) {if (is_array($options['noshuffle'])) {$noshuffle = $options['noshuffle'][$partnum];} else {$noshuffle = $options['noshuffle'];}}

        if (!is_array($questions) || !is_array($answers)) {
            echo _('Eeek!  $questions or $answers is not defined or needs to be an array.  Make sure both are defined in the Common Control section.');
            return 0;
        }
        if ($multi) { $qn = ($qn+1)*1000+$partnum; }
        $score = 1.0;
        $deduct = 1.0/count($questions);
        if ($noshuffle=="questions" || $noshuffle=='all') {
            $randqkeys = array_keys($questions);
        } else {
            $randqkeys = $RND->array_rand($questions,count($questions));
            $RND->shuffle($randqkeys);
        }
        if ($noshuffle=="answers" || $noshuffle=='all') {
            $randakeys = array_keys($answers);
        } else {
            $randakeys = $RND->array_rand($answers,count($answers));
            $RND->shuffle($randakeys);
        }
        if (isset($matchlist)) {$matchlist = array_map('trim',explode(',',$matchlist));}

        $origla = array();
        for ($i=0;$i<count($questions);$i++) {
            if ($i>0) {$GLOBALS['partlastanswer'] .= "|";} else {$GLOBALS['partlastanswer']='';}
            $GLOBALS['partlastanswer'] .= $_POST["qn$qn-$i"];
            if ($_POST["qn$qn-$i"]!="" && $_POST["qn$qn-$i"]!="-") {
                if (!is_numeric($_POST["qn$qn-$i"])) { //legacy
                    $qa = ord($_POST["qn$qn-$i"]);
                    if ($qa<97) { //if uppercase answer
                        $qa -= 65;  //shift A to 0
                    } else { //if lower case
                        $qa -= 97;  //shift a to 0
                    }
                } else {
                    $qa = Sanitize::onlyInt($_POST["qn$qn-$i"]);
                }
                $origla[$randqkeys[$i]] = $randakeys[$qa];
                if (isset($matchlist)) {
                    if ($matchlist[$randqkeys[$i]]!=$randakeys[$qa]) {
                        $score -= $deduct;
                    }
                } else {
                    if ($randqkeys[$i]!=$randakeys[$qa]) {
                        $score -= $deduct;
                    }
                }
            } else {$origla[$randqkeys[$i]] = '';$score -= $deduct;}
        }
        ksort($origla);
        $GLOBALS['partlastanswer'] .= '$!$'.implode('|',$origla);
        return $score;
    }
}
