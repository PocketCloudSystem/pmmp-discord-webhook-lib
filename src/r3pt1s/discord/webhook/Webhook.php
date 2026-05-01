<?php

namespace r3pt1s\discord\webhook;

use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\Server;
use r3pt1s\discord\webhook\message\Message;
use r3pt1s\discord\webhook\task\DiscordSendDataTask;

final class Webhook {

    private string $defaultUsername = "";
    private string $defaultAvatarUrl = "";

    /**
     * @param string $url The base discord webhook url
     */
    public function __construct(private readonly string $url) {}

    public function withDefaults(string $defaultUsername, string $defaultAvatarUrl): self {
        $this->defaultUsername = $defaultUsername;
        $this->defaultAvatarUrl = $defaultAvatarUrl;
        return $this;
    }

    public function createMessage(bool $wait = false, ?string $threadId = null, bool $withComponents = false): Message {
        return new Message($wait, $threadId, $withComponents, $this)->tap(function (Message $message): void {
            if ($this->defaultUsername !== "") $message->setUsername($this->defaultUsername);
            if ($this->defaultAvatarUrl !== "") $message->setAvatarUrl($this->defaultAvatarUrl);
        });
    }

    public function send(Message $message): Promise {
        $promise = new PromiseResolver();
        Server::getInstance()->getAsyncPool()->submitTask(new DiscordSendDataTask(
            $this->getUrl(),
            $message->isWait(),
            $message->getThreadId(),
            $message->isWithComponents(),
            serialize($message->write()),
            static function (bool|string $response, int $statusCode, string $curlError, string $curlErrno) use ($promise): void {
                $promise->resolve([$response, $statusCode, $curlError, $curlErrno]);
            }
        ));

        return $promise->getPromise();
    }

    public function getUrl(): string {
        return $this->url;
    }

    public static function create(string $url): self {
        return new self($url);
    }
}