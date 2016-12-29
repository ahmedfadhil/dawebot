<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Commands\SystemCommand;


use Longman\TelegramBot\Entities\Gamer;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ReplyKeyboardMarkup;
use Longman\TelegramBot\Entities\ReplyKeyboardHide;


/**
 * Generic message command
 */
class GenericmessageCommand extends SystemCommand
{
    /**#@+
     * {@inheritdoc}
     */
    protected $name = 'Genericmessage';
    protected $description = 'Handle generic message';
    protected $version = '1.0.2';
    protected $need_mysql = true;
protected $respuestas = array('1'=>'A','2'=>'B','3'=>'C','4'=>'D');
    /**#@-*/

private function setQuestion($gamer){
                $questionRow = DB::getCurrentQuestion($gamer->getCurrentQuestion(), $gamer->getUserId(), $gamer->getCurrentQuiz());
//      error_log("Gamer Current /ID " .  $gamer->getCurrentQuestion() . " " . $gamer->getUserId() . "\n", 3, "/tmp/error.log");

                $textRow = DB::getQuestion($questionRow['current_question_id']);

                // $gamer->setCurrentQuestionText(str_replace("\\","\n",$textRow['texto']));
                $gamer->setCurrentQuestionText($textRow['texto']);
        //error_log("Gamer Current Text " .  $textRow['texto'] . "\n", 3, "/tmp/error.log");

                $variants = array();
                for($i=1; $i<=4; $i++)
                        if ($textRow['resp'.$i] != '')
                                $variants[] = $textRow['resp'.$i];

                $gamer->setVariants($variants);


                $gamer->setCurrentQuestionAnswer($textRow['respOK']);

                $numQuestRow = DB::getNumQuest($gamer->getCurrentQuiz());
                $gamer->setNumQuestion($numQuestRow['numQuest']);
     }


public function findGamer($chatID){

        $gamer =null; $error = null;
        $result = DB::findGamer($chatID);
	error_log("RESULT: ". print_r($result, 1) ."\n", 3, "/tmp/error.log");

        if ($result==null){
        error_log("No se pudo encontrar el gamer\n", 3, "/tmp/error.log");
                $error = "No se pudo encontrar el gamer";
        }else {

                        $gamer = new Gamer(
                                array(
                                        'user_id'=>$result['user_id'],
                                        'chat_id'=>$result['chat_id'],
                                        'currentQuestion'=>$result['currentQuestion'],
                                        'currentQuiz'=>$result['currentQuiz'],
                                        'rightAnswers'=>$result['rightAnswers'],
                                        'wrongAnswers'=>$result['wrongAnswers']
                                )
                        );

                $this->setQuestion($gamer);
        }

        return array($gamer, $error);

    }




 public function findOrCreateGamer($data){
            // TODO: chat_id == user_id ??
            $chatID = $data['chat_id'];
            $userID = $data['chat_id'];

            list($gamer, $err) = $this->findGamer($chatID);

            if ($err != null){ // primera vez que vemos a este usuario, hay que guardarlo en la BBDD
                // die("Error: " . $err . "\n");
                $gamer = new Gamer(array('chat_id'=>$chatID, 'user_id'=>$userID));
                $gamer->handleStart();
                $err = DB::saveGamer($gamer);
                if($err!=null){
                        error_log("No se pudo grabar el gamer \n", 3, "/tmp/error.log");
                        return null;
                }
            }else{
                error_log("Found Gamer with id $chatID \n", 3, "/tmp/error.log");
            }

            $gamer->setUserID($userID);
            return $gamer;
    }


 private function filter($variants){
            $res = "\n";
            $ind = 1;
            foreach($variants as $variant){
                    if ($variant != "")
                            $res .= $this->respuestas[$ind] . ") " . $variant . "\n";
                    $ind++;
            }
            return $res;

    }

private function getKeyBoard($numQuest){

        #Keyboard examples
        $keyboards = array();

        $keyboard[] = ['A','B'];
        $keyboard[] = ['C','D'];

        $keyboards[] = $keyboard;
        unset($keyboard);

        $keyboard[] = ['A','B'];
        $keyboard[] = ['C'];

        $keyboards[] = $keyboard;
        unset($keyboard);


        $keyboard[] = ['A','B'];
	$keyboards[] = $keyboard;
	unset($keyboard);

        $reply_keyboard_markup = new ReplyKeyboardMarkup(
            [
                'keyboard' => $keyboards[(4 - $numQuest)] ,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
                'selective' => false
            ]
        );
        #echo $json;
        return $reply_keyboard_markup;


    }


    /**
     * Execution if MySQL is required but not available
     *
     * @return boolean
     */
    public function executeNoDb()
    {
        //Do nothing
        return Request::emptyResponse();
    }

  private function getWhynotnext(){
    return new ReplyKeyboardMarkup(
            [
                'keyboard' => [ 
			['Why', 'WhyNot', 'Next']
		  ] ,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
                'selective' => true,
            ]
        );

  }

private function showQuestion($data, $gamer){
	$data['text'] = $gamer->getCurrentQuestionText() . $this->filter($gamer->getVariants());
        $data['parse_mode'] = "HTML";
        $data['reply_markup'] = $this->getKeyBoard(sizeof($gamer->getVariants()));
        $result = Request::sendMessage($data);

        if ($gamer->getCurrentQuestion() == 1 && $gamer->getCurrentQuiz() == 3){
                $result = Request::sendPhoto($data, $this->telegram->getUploadPath().'/'.'g1.png');
        }
        if ($gamer->getCurrentQuestion() == 3 && $gamer->getCurrentQuiz() == 7){
                $result = Request::sendPhoto($data, $this->telegram->getUploadPath().'/'.'g2.png');
        }

	return $result;
}
    /**
     * Execute command
     *
     * @return boolean
     */
    public function execute()
    {
        error_log(__METHOD__ . "\n", 3, "/tmp/error.log");
        //If a conversation is busy, execute the conversation command after handling the message
        $conversation = new Conversation(
            $this->getMessage()->getFrom()->getId(),
            $this->getMessage()->getChat()->getId()
        );
        //Fetch conversation command if it exists and execute it
        if ($conversation->exists() && ($command = $conversation->getCommand())) {
            return $this->telegram->executeCommand($command, $this->update);
        }

 
        $message = $this->getMessage();
        //You can use $command as param
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();
        $command = $message->getCommand();
 	$text = $message->getText(true);
        // TODO: user_id == chat_id ??
        $gamer = $this->findOrCreateGamer( array( 'chat_id' => $chat_id, 'user_id' => $chat_id));
	// error_log(print_r($gamer, 1), 3, "/tmp/error.log");
        $data = array();
        $data['chat_id'] = $chat_id;

  if (in_array($text, array_values($this->respuestas))){ // es una respuesta del rango A..D
	if ($gamer->handleAnswer($text))
		$data['text'] = "👍 Right!";
	else {
		$data['text'] = "😕 Wrong, " . strtoupper( $this->respuestas[$gamer->getCurrentQuestionAnswer()] );
	}
	$data['reply_markup'] = $this->getWhynotnext(); 
	$gamer->save();
	$result = Request::sendMessage($data);
  } else if ($text == 'Next') {
	$gamer->nextQuestion();
	$gamer->save();
	// error_log(print_r($gamer,1), 3, "/tmp/error.log");
	$this->setQuestion($gamer);
	$result = $this->showQuestion($data, $gamer);
} else if (strtolower($text) =="Resume"){
	$data = array();
	$data['chat_id'] = $chat_id;
	
        $result = $this->showQuestion($data, $gamer);
	
} else if (strtolower($text) == "why") {
	$data = array();
        $data['chat_id'] = $chat_id;
	$question = DB::getCurrentQuestion($gamer->getCurrentQuestion(), $gamer->getUserId(), $gamer->getCurrentQuiz());
        $data['text'] = $question['why']?$question['why']:"No hay texto why definido";
        $data['reply_markup'] = $this->getWhynotnext();
        $result = Request::sendMessage($data);
} else if (strtolower($text) == "whynot"){
	$data = array();
        $data['chat_id'] = $chat_id;
	$question = DB::getCurrentQuestion($gamer->getCurrentQuestion(), $gamer->getUserId(), $gamer->getCurrentQuiz());
        $data['text'] = $question['whynot']?$question['whynot']:"No hay texto whynot definido";
        $data['reply_markup'] = $this->getWhynotnext();
        $result = Request::sendMessage($data);
} else {
	$data = array();
        $data['chat_id'] = $chat_id;
        $data['text'] = "Please select one of these options:";
        $data['reply_markup'] = $this->getWhynotnext();
        $result = Request::sendMessage($data);
 }


  return $result;
}

}
