<?php
namespace Haxibiao\Question\Traits;

use App\Category;
use App\CategoryUser;
use App\Exceptions\UserException;
use App\ForkAnswer;
use App\ForkQuestion;
use App\Image;
use App\User;
use App\UserAction;

trait ForkQuestionRepo
{

    /**
     * 获取分支题/分数题（如心理测试题）
     */
    public static function testQuestions($user, $category_id, $limit = 10)
    {
        $category = Category::find($category_id);
        if (empty($category)) {
            throw new UserException('该分类不存在');
        }
        CategoryUser::firstOrCreate([
            'user_id'     => $user->id,
            'category_id' => $category_id,
        ]);

        //记录一下用户行为
        $action = UserAction::firstOrCreate(['user_id' => $user->id]);
        $action->addVisitedCategoryId($category_id)->save();

        //避免n+1查询
        $qb = $category->forkQuestions()->with(['category', 'user', 'image', 'video']);

        $questions = $qb->take($limit)->get();

        //预加载前端定义字段关联关系
        $questions->load(['user', 'user.profile', 'video', 'image']);

        //预加喜欢状态
        // $questions = ForkQuestion::loadFavoriteStatus($user, $questions);
        //预加载点赞状态
        // $questions = ForkQuestion::loadLiked($user, $questions);

        return $questions;
    }

    public function saveImage($base64DataString)
    {
        $image = Image::saveImage($base64DataString);
        if (!empty($image)) {
            $this->image_id = $image->id;
            $this->update(['image_id' => $image->id]);
            // $this->save();
            return $image->id;
        }

        return $this;
    }

    public static function recordForkQuestionResult(User $user, ForkQuestion $forkQuestion, $answer)
    {
        $forkQuestion->category->increment('answers_count');
        $explanations = $forkQuestion->forkExplanations;
        // dd($explanations);
        foreach ($explanations as $explanation) {
            //匹配解析
            if ($explanation->answer == $answer) {
                //保存回答记录
                $forkAnswer = ForkAnswer::firstOrNew([
                    'user_id'          => $user->id,
                    'fork_question_id' => $forkQuestion->id,
                ]);

                if ($forkAnswer->id) {
                    $forkAnswer->update(['answer' => $answer]);
                } else {
                    $forkAnswer->answer = $answer;
                    $forkAnswer->save();
                }
            }
        }
        return ForkAnswer::where('fork_question_id', $forkQuestion->id)
            ->where('user_id', '!=', $user->id)
            ->latest('created_at')
            ->where('answer', $answer)
            ->take(5)
            ->get();
    }

}
