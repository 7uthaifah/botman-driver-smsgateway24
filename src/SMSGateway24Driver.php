<?php

namespace BotMan\Drivers\SMSGateway24;

use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Users\User;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BotMan\Drivers\SMSGateway24\Exceptions\SMSGateway24Exception;
use BotMan\BotMan\Messages\Attachments\Location;
use Symfony\Component\HttpFoundation\ParameterBag;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\Drivers\SMSGateway24\Exceptions\UnsupportedAttachmentException;

class SMSGateway24Driver extends HttpDriver
{
    protected $headers = [];

    const DRIVER_NAME = 'SMSGateway24';

    const API_BASE_URL = 'https://smsgateway24.com';

    /**
     * @param Request $request
     * @return void
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
        $this->headers = $request->headers->all();
        $this->event = Collection::make($this->payload);
        $this->config = Collection::make($this->config->get('smsgateway24', []));
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        // No incoming
        return false;
    }

    /**
     * Retrieve the chat message(s).
     *
     * @return array
     */
    public function getMessages()
    {
        return [];
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->config->get('token')) && !empty($this->config->get('device_id')) && !empty($this->config->get('sim'));
    }

    /**
     * Retrieve User information.
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        return new User($matchingMessage->getSender(), null, null, $matchingMessage->getRecipient());
    }


    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Convert a Question object into a valid message
     *
     *
     * @param \BotMan\BotMan\Messages\Outgoing\Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $buttons = $question->getButtons();

        if ($buttons) {
            $options =  Collection::make($buttons)->transform(function ($button) {
                return $button['text'];
            })->toArray();

            return $question->getText() . "\n" . implode("\n", $options);
        }
    }


    /**
     * @param OutgoingMessage|\BotMan\BotMan\Messages\Outgoing\Question $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     * @throws UnsupportedAttachmentException
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $payload = [
            'sendto' => $matchingMessage->getSender(),
        ];

        // No question, image or file attach till now
        if ($message instanceof OutgoingMessage) {
            $attachment = $message->getAttachment();

            if ($attachment instanceof Image || $attachment instanceof Video) {
                throw new UnsupportedAttachmentException('The '. get_class($attachment) . ' is not supported (currently: text)');
            
            } else {
                $payload['body'] = $message->getText();
            }
            
        } elseif ($message instanceof Question) {
            throw new UnsupportedAttachmentException('The '. get_class($attachment) . ' is not supported (currently: text)');
        }

        return $payload;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        // Remove any BMP characters from message
        $payload['body'] = trim(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $payload['body']));

        // Add config params
        $payload['token'] = $this->config->get('token');
        $payload['device_id'] = $this->config->get('device_id');
        $payload['sim'] = $this->config->get('sim');

        // No image or file attach
        $endpoint = '/getdata/addsms';

        return $this->http->post(self::API_BASE_URL . $endpoint, $payload, [], [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function types(IncomingMessage $matchingMessage)
    {
        // Do nothing
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $payload = array_merge_recursive([
            'sendto' => $matchingMessage->getRecipient(),
        ], $parameters);


        return $this->sendPayload($payload);
    }

}
