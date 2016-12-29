<?php

/*
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/
namespace Longman\TelegramBot\Entities;

use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;

class Gamer extends Entity
{

    protected $user_id;
    protected $chat_id;
    protected $currentQuestion;
    protected $currentQuestionText;
    protected $currentQuiz;
    protected $rightAnswers;
    protected $wrongAnswers;
    protected $respuestas = array('1'=>'A','2'=>'B','3'=>'C','4'=>'D');
    protected $variants = array();

    public function __construct(array $data)
    {

        $this->user_id = isset($data['user_id']) ? $data['user_id'] : null;
        if (empty($this->user_id)) {
            throw new TelegramException('user_id is empty!');
        }

        $this->chat_id = isset($data['chat_id']) ? $data['chat_id'] : null;
        if (empty($this->chat_id)) {
            throw new TelegramException('chat_id is empty!');
        }

        $this->currentQuestion = isset($data['currentQuestion']) ? $data['currentQuestion'] : 1;
        $this->currentQuiz = isset($data['currentQuiz']) ? $data['currentQuiz'] : 1;

        $this->rightAnswers = isset($data['rightAnswers']) ? $data['rightAnswers'] : 0;
        $this->wrongAnswers = isset($data['wrongAnswers']) ? $data['wrongAnswers'] : 0;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function getChatId()
    {
        return $this->chat_id;
    }

    public function getCurrentQuestion()
    {
        return $this->currentQuestion;
    }

    public function setCurrentQuestion($questNum){
		$this->currentQuestion = $questNum;
	}
    public function getCurrentQuiz()
    {
	    return $this->currentQuiz;
    }
    public function getRightAnswers()
    {
        return $this->rightAnswers;
    }

    public function getWrongAnswers()
    {
        return $this->wrongAnswers;
    }

    public function handleStart(){
	$this->currentQuestion = 1;
    }

    public function nextQuestion(){
	    $this->currentQuestion = (int)$this->currentQuestion + 1;
	    if ($this->currentQuestion > $this->getNumQuestion())
		    $this->currentQuestion = 1;
	   // error_log("nextQuestion:". $this->currentQuestion, 3, "/tmp/error.log"); 
    }

    public function save(){
	    // error_log("Grabando..." . $this->currentQuestion . "\n", 3, "/tmp/error.log");
	    $todoOK = DB::saveGamer($this);
	    // handle Exceptions
	    if (!$todoOK) die("Error al guardar el gamer");
    }

    public function setUserID($id){
	$this->user_id = $id;	
    }


    public function handleAnswer($answer){
	    $acierto = (strtoupper($answer) == $this->respuestas[$this->getCurrentQuestionAnswer()]);
	    DB::saveAnswer( array("user"=>$this->getUserId(), "question"=>$this->getCurrentQuestion(), "result"=>$acierto, "quiz"=>$this->getCurrentQuiz()));
	    if ($acierto)
		    $this->rightAnswers++;
	    else 
		    $this->wrongAnswers++;
	    return $acierto;
    }

    public function setCurrentQuiz($currentQuiz){
	    $this->currentQuiz = $currentQuiz;
    }

    public function setCurrentQuestionAnswer($text){
	    $this->currentQuestionAnswer = $text;
    }

    public function getCurrentQuestionAnswer(){
	return $this->currentQuestionAnswer;
    }

    public function setCurrentQuestionText($text){
	$this->currentQuestionText = $text;
    }

    public function getCurrentQuestionText(){
	return $this->currentQuestionText;
    }

    public function setNumQuestion($numQuest){
	$this->numQuest = $numQuest;
    }
    public function getNumQuestion(){ // número de preguntas en el quiz actual
    	return $this->numQuest;
    }

    public function setVariants($variants){
	$this->variants = $variants;
    }

    public function getVariants(){
	return $this->variants;
    }

    public function changeQuiz($quiz){
	$this->setCurrentQuiz($quiz);
	$this->setCurrentQuestion(1);
    }

// GetAccuracy - return persentage of right answers
    public function getAccuracy(){
	    if ($this->rightAnswers + $this->wrongAnswers == 0)
		return 0.0;
	
	return ($this->rightAnswers) / ($this->rightAnswers + $this->wrongAnswers);
    }  
  }
