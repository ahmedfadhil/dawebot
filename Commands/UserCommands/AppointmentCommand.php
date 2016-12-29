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

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ForceReply;
use Longman\TelegramBot\Entities\ReplyKeyboardHide;
use Longman\TelegramBot\Entities\ReplyKeyboardMarkup;
use \DateTime;
use \DateTimeZone;
use Google_Client;
use Google_Auth_AssertionCredentials;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;

/**
 * User "/appointment" command
 */
class AppointmentCommand extends UserCommand
{
    /**#@+
     * {@inheritdoc}
     */
    protected $name = 'appointment';
    protected $description = 'Solicitar tutoría presencial con el profesor';
    protected $usage = '/appointment';
    protected $version = '0.0.1';
    protected $need_mysql = true;
    /**#@-*/

    /**
     * Conversation Object
     *
     * @var Longman\TelegramBot\Conversation
     */
    protected $conversation;

    private function reserve($datetime, $user)
    {

        define('APPLICATION_NAME', 'Google Calendar API PHP Write');

        $service_account_name = 'xxxxxx@yyyyyy.iam.gserviceaccount.com';
        $key_file_location = __dir__ . "/API-dxxxxxxxx.json";

        $client = new Google_Client();
        $client->setApplicationName("Whatever the name of your app is");

        $key = file_get_contents($key_file_location);
        $data = json_decode($key);

        $cal_id = "xxxxxx@gmail.com";

        $cred = new Google_Auth_AssertionCredentials(
            $service_account_name,
            array('https://www.googleapis.com/auth/calendar','https://www.googleapis.com/auth/calendar.readonly'),
            $data->private_key
        );
        $client->setAssertionCredentials($cred);
        if ($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion($cred);
        }

        $calendarService = new Google_Service_Calendar($client);

        $event = new Google_Service_Calendar_Event();
        $event->setSummary("Tutoría");
        $event->setDescription(print_r($user, 1));

        $start = new Google_Service_Calendar_EventDateTime();
        $start->setDateTime((new DateTime($datetime, new DateTimeZone('Europe/Madrid')))->format(DateTime::RFC3339));
        $event->setStart($start);

        $end = new Google_Service_Calendar_EventDateTime();
        $end->setDateTime(
            (new DateTime($datetime, new DateTimeZone('Europe/Madrid')))->add(date_interval_create_from_date_string("1 hour"))->format(DateTime::RFC3339)
        );
        $event->setEnd($end);

        $createdEvent = $calendarService->events->insert($cal_id, $event);

//	    $end->setDateTime("2016-04-28T09:00:00");

    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        error_log(__METHOD__ . "\n", 3, "/tmp/error.log");
        $message = $this->getMessage();

        $chat = $message->getChat();
        $user = $message->getFrom();
        $text = $message->getText(true);

        $chat_id = $chat->getId();
        $user_id = $user->getId();

        //Preparing Respose
        $data = [];
        if ($chat->isGroupChat() || $chat->isSuperGroup()) {
            //reply to message id is applied by default
            $data['reply_to_message_id'] = $message_id;
            //Force reply is applied by default to so can work with privacy on
            $data['reply_markup'] = new ForceReply([ 'selective' => true]);
        }
        $data['chat_id'] = $chat_id;

        //Conversation start
        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());

        //cache data from the tracking session if any
        if (!isset($this->conversation->notes['state'])) {
            $state = '0';
        } else {
            $state = $this->conversation->notes['state'];
        }

        //state machine
        //entrypoint of the machine state if given by the track
        //Every time the step is achived the track is updated
        switch ($state) {
            case 0:
                if (empty($text)) {
                    $this->conversation->notes['state'] = 0;
                    $this->conversation->update();

                    $data['text'] = 'Please, select a date for the appointment :';

                    // first and second thursday
                    $thursday = new DateTime();
                    $thursday->modify('next thursday');
                    $secthursday = clone $thursday;
                    $secthursday->modify('next thursday');

                    // first and second friday
                    $friday = new DateTime();
                    $friday->modify('next friday');
                    $secfriday = clone $friday;
                    $secfriday->modify('next friday');

                    $keyboard = [[$thursday->format('Y-m-d'), $friday->format('Y-m-d')], [$secthursday->format('Y-m-d'), $secfriday->format('Y-m-d')]];

                    // $keyboard = [['2016-04-28','2016-04-28'], ['2016-04-28', '2016-04-28']];
                    $reply_keyboard_markup = new ReplyKeyboardMarkup(
                        [
                            'keyboard' => $keyboard ,
                            'resize_keyboard' => true,
                            'one_time_keyboard' => true,
                            'selective' => true
                        ]
                    );
                    $data['reply_markup'] = $reply_keyboard_markup;

            //          $data['reply_markup'] = new ReplyKeyBoardHide(['selective' => true]);
                    $result = Request::sendMessage($data);
                    break;
                }
                $this->conversation->notes['date'] = $text;
                $text = '';
                // no break
            case 1:
                if (empty($text)) {
                    $now = new DateTime($this->conversation->notes['date'] . " 11:30", new DateTimeZone('Europe/Madrid'));
                    $end = clone $now;
                    $end->add(date_interval_create_from_date_string("2 hours"));

                    $slots = [];
                // search for free slots
                    while ($now <= $end) {
                        array_push($slots, clone $now);

                        $now->add(date_interval_create_from_date_string("30 minutes"));
                    }

                    $keyboard = [ [ $slots[0]->format('H:i'), $slots[1]->format('H:i') ], [ $slots[2]->format('H:i'), $slots[3]->format('H:i') ] ];
                    $this->conversation->notes['state'] = 1;
                    $this->conversation->update();
                    $reply_keyboard_markup = new ReplyKeyboardMarkup(
                        [
                            'keyboard' => $keyboard ,
                            'resize_keyboard' => true,
                            'one_time_keyboard' => true,
                            'selective' => true
                        ]
                    );
                    $data['reply_markup'] = $reply_keyboard_markup;

    
                    $data['text'] = 'Choose a free slot:';
                    $result = Request::sendMessage($data);
                    break;
                }
                $this->conversation->notes['hour'] = $text;
                ++$state;
                $text = '';
            case 3:
                $datetime = $this->conversation->notes['date'] . " " .
                        $this->conversation->notes['hour'];
          
                $this->reserve($datetime, $user);
                $out_text = '/appointment result:' . "\n";
                unset($this->conversation->notes['state']);
                foreach ($this->conversation->notes as $k => $v) {
                    $out_text .= "\n" . ucfirst($k).': ' . $v;
                }

                $data['reply_markup'] = new ReplyKeyBoardHide(['selective' => true]);
                $data['text'] = $out_text;
                $this->conversation->stop();
                $result = Request::sendMessage($data);
                break;
        }
        return $result;
    }
}
