<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;

use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\Gamer;
/**
 * User "/status" command
 */
class StatusCommand extends UserCommand
{
    /**#@+
     * {@inheritdoc}
     */
    protected $name = 'status';
    protected $description = 'Mostrar estadísticas del alumno';
    protected $usage = '/status or /status <command>';
    protected $version = '1.0.1';
    /**#@-*/


   
    public function findGamer($chatID){

	$gamer =null; $error = null;
	$result = DB::findGamer($chatID);
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
	}

	return array($gamer, $error);

    }

    public function findOrCreateGamer($data){
	    // TODO: chat_id == user_id ??
	    $chatID = $data['chat_id'];
	    $userID = $data['chat_id'];

	    list($gamer, $err) = $this->findGamer($chatID);

	    if ($err != null){ // primera vez que vemos a este usuario, hay que guardarlo en la BBDD
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

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();

        $message_id = $message->getMessageId();
        $command = trim($message->getText(true));

        //Only get enabled Admin and User commands
        $commands = array_filter($this->telegram->getCommandsList(), function ($command) {
            return (!$command->isSystemCommand() && $command->isEnabled());
        });

        $gamer = $this->findOrCreateGamer( array( 'chat_id' => $chat_id, 'user_id' => $chat_id));
  	$text = "Tu puntuación:\nRespuestas correctas: " . $gamer->getRightAnswers() . 
			"\nRespuestas incorrectas: ". $gamer->getWrongAnswers() . "\nPrecisión: ". $gamer->getAccuracy()*100 ;


        $data = [
            'chat_id'             => $chat_id,
            'reply_to_message_id' => $message_id,
            'text'                => $text,
        ];

        return Request::sendMessage($data);
    }
}
