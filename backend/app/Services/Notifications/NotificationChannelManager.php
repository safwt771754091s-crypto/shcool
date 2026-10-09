<?php

namespace App\Services\Notifications;

use App\Services\Notifications\Contracts\NotificationChannel;
use InvalidArgumentException;

/**
 * Registry of delivery transports.
 *
 * Channels are resolved by name; the manager keeps them decoupled from the
 * service so new providers (e.g. a different SMS gateway) only need a class
 * and a container binding.
 */
class NotificationChannelManager
{
    /** @var array<string, NotificationChannel> */
    protected array $channels = [];

    /**
     * @param  iterable<NotificationChannel>  $channels
     */
    public function __construct(iterable $channels = [])
    {
        foreach ($channels as $channel) {
            $this->register($channel);
        }
    }

    public function register(NotificationChannel $channel): void
    {
        $this->channels[$channel->name()] = $channel;
    }

    public function channel(string $name): NotificationChannel
    {
        if (! isset($this->channels[$name])) {
            throw new InvalidArgumentException("Notification channel [{$name}] is not registered.");
        }

        return $this->channels[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    /**
     * Names of channels whose credentials are present and that can deliver now.
     *
     * @return list<string>
     */
    public function available(): array
    {
        return array_values(array_map(
            fn (NotificationChannel $channel) => $channel->name(),
            array_filter($this->channels, fn (NotificationChannel $channel) => $channel->isConfigured()),
        ));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->channels);
    }
}
