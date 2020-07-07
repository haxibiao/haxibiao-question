<?php


namespace Haxibiao\Question\Controllers\Api;


use App\Http\Controllers\Controller;
use Haxibiao\Question\Explanation;
use Haxibiao\Question\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{

    public function index(Request $request)
    {
        $categoryId = $request->get('category_id');
        $user       = auth()->user();
        if (empty($categoryId)) {
            return failed_response(500, '参数错误,分类不存在');
        }

        return Question::getQuestions($user, $categoryId, 10);
    }

    public function show($id)
    {
        $question = Question::with(['user', 'video'])->publish()->find($id);

        if (is_null($question)) {
            return failed_response(500, '题目不存在');
        }

        return $question;
    }

    public function importQuestion(Request $request)
    {
        if ($data = $request->get('data')) {

            $data     = json_decode($data, true);
            $question = Question::firstOrNew($data);

            if (!isset($question->id)) {
                $question->fill([
                    'ticket'    => Question::DEFAULT_TICKET,
                    'gold'      => Question::DEFAULT_GOLD,
                    'review_id' => Question::max('review_id') + 1,
                ]);
                $question->type = is_null($question->video_id) ? Question::TEXT_TYPE : Question::VIDEO_TYPE;
                $question->getDefaultRank();
                return (int) $question->save();
            }

            return -1;
        }
        abort(404);
    }

    public function importExplanation(Request $request)
    {
        if ($data = $request->get('data')) {
            $data        = json_decode($data, true);
            $explanation = Explanation::firstOrNew($data);

            if (!isset($explanation->id)) {
                $explanation->setDefaultType();
                $explanation->save();
            }

            return $explanation;
        }
    }

    public function importVideo(Request $request)
    {
        $questionId = $request->get('question_id');
        $videoId    = $request->get('video_id');

        if (empty($questionId) || empty($videoId)) {
            return failed_response(500, '导入失败,参数不完整!');
        }

        $question = Question::find($questionId);

        if (is_null($question)) {
            return failed_response(500, '导入失败,题目不存在!');
        }

        $question->video_id = $videoId;
        $question->type     = Question::VIDEO_TYPE;

        if (is_null($question->video)) {
            return failed_response(500, '导入失败,视频不存在!');
        }

        $question->save();

        return successful_response(200, $question);
    }

    public function importExplanationVideo(Request $request)
    {
        $explanationId = $request->get('explanation_id');
        $videoId       = $request->get('video_id');

        if (empty($explanationId) || empty($videoId)) {
            return failed_response(500, '导入失败,参数不完整!');
        }

        $explanation = Explanation::find($explanationId);

        if (is_null($explanation)) {
            return failed_response(500, '导入失败,解析不存在!');
        }

        $explanation->video_id = $videoId;
        $explanation->setDefaultType();

        if (is_null($explanation->video)) {
            return failed_response(500, '导入失败,视频不存在!');
        }

        $explanation->save();

        return successful_response(200, $explanation);
    }

    public function getExplantion(Request $request)
    {
        $videoId = $request->get('video_id');

        if (empty($videoId)) {
            return failed_response(500, '参数错误!');
        }

        $explanation = Explanation::where('video_id', $videoId)->first();

        return successful_response(200, $explanation);
    }

    public function more(Request $request)
    {

        $category_id = $request->get('category_id');
        $questions   = Question::where('submit', 1)->where('category_id', $category_id)->paginate(10);
        //下面是为了兼容VUE
        foreach ($questions as $question) {
            // $question->updated_at = $question->updated_at->toDateTimeString();
            // dd($question->updated_at);
            // $question->created_at = $question->created_at->toDateTimeString();
            $question->putTime  = diffForHumansCN($question->created_at);
            $question->user     = $question->user;
            $question->category = $question->category;
            $question->image    = $question->image;
            $question->video    = $question->video;
        }
        return $questions;
    }

    public function recommend(Request $request)
    {

        $questions = Question::where('submit', 1)->paginate(10);
        //下面是为了兼容VUE
        foreach ($questions as $question) {
            $question->putTime  = diffForHumansCN($question->created_at);
            $question->category = $question->category;
            $question->user     = $question->user;
            $question->image    = $question->image;
            $question->video    = $question->video;
        }
        return $questions;
    }
}
