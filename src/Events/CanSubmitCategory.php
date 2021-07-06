<?php

namespace Haxibiao\Question\Events;

use Haxibiao\Question\CategoryUser;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class CanSubmitCategory implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $categoryUser;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(CategoryUser $categoryUser)
    {
        $this->categoryUser = $categoryUser;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
		if(in_array(config('app.name'),['haxibiao','yinxiangshipin'])){
			return new PrivateChannel(config('app.name').'.User.' . $this->categoryUser->user_id);
		}
        return new PrivateChannel('App.User.' . $this->categoryUser->user_id);
    }

    public function broadcastWith()
    {
        $category = \App\Category::find($this->categoryUser->category_id);
        if ($category) {
            return [
                "title"   => "恭喜解锁出题权限",
                "content" => "恭喜您已经解锁了当前分类的出题权限，快去出题试试吧！",
                "name"    => $category->name,
                "id"      => $category->id,
            ];
        }
    }
}
