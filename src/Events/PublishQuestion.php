<?php

namespace Haxibiao\Question\Events;


use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;

class PublishQuestion
{
    use Dispatchable, InteractsWithSockets;

    public $question;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($question)
    {
        $this->question = $question;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
		if(in_array(config('app.name'),['haxibiao','yinxiangshipin'])){
			return new PrivateChannel(config('app.name').'.channel-name');
		}
        return new PrivateChannel('channel-name');
    }
}
