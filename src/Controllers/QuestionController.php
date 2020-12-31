<?php

namespace Haxibiao\Question\Controllers;

use App\Http\Controllers\Controller;
use Haxibiao\Question\Category;
use Haxibiao\Question\Question;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categories = Category::where('status', 1)->orderBy('rank')->paginate(10);
        return view('question.index')->withCategories($categories);
    }

    //根据题库id展示10个该类型的题目
    public function questions($category_id)
    {
        $questions = Question::where("category_id", $category_id)
            ->where('submit', 1)
            ->orderBy('rank', 'desc')->paginate(10);
        $category = Category::findOrFail($category_id);

        return view('question.index')
            ->withQuestions($questions)
            ->withCategory($category);
    }

    /**
     * 根据ID返回一个问题的题目和选项
     * 并根据分类，随机返回4条相关题目.
     * 不与当前题目相同.
     *
     * @param string $code
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // 根据ID查找问题
        $question = Question::where('id', $id)
            ->first() ?? abort(404);

        $selections = $question->selectionsToArray();

        //相关题目
        $about = Question::inRandomOrder()
            ->where('category_id', $question->category_id)
            ->where('id', '!=', $id)
            ->limit(4)
            ->get();

        $question->putTime  = diffForHumansCN($question->created_at);
        $question->category = $question->category;
        $question->user     = $question->user;
        $question->image    = $question->image;
        $question->video    = $question->video;

        \track_web("网页", "题目详情");

        return view('question.answer')
            ->withQuestion($question)
            ->withSelections($selections)
            ->withCategory($question->category)
            ->withAbout($about);
    }

    /**
     * 根据题目ID获取答案.
     *
     * @param int $id 题目ID
     *
     * @return string 答案 => B 解放碑
     */
    public function getAnswer($id)
    {
        $question = Question::findorFail($id);

        $selections = $question->selectionsToArray();
        $answer     = $question->answer;
        foreach ($selections as $selection) {
            if ($answer == $selection['Value']) {
                $answer = $answer . '： ' . $selection['Text'];
                break;
            }
        }
        $answer = $this->deleteSymbol($answer);
        //直接返回答案字符串 => B 解放碑
        return response($answer);
    }

    /**
     * 删除题目选项中的特殊符号.替换为空格
     *
     * @param string $selections 选项字符串
     *
     * @return string 替换后的字符串
     */
    public function deleteSymbol($selections)
    {
        $selections = str_ireplace('；', ' ', $selections);
        $selections = str_ireplace('。', ' ', $selections);

        return $selections;
    }

    /**
     * 获取热门题目.
     */
    public function hotQuestion()
    {
        $questions = Question::orderBy('updated_at', 'desc')
            ->paginate(15);

        return response()->json($questions);
    }

    public function qrcode($code)
    {
        $url    = sprintf('%s/questions/%s', config('app.url'), $code);
        $qrcode = QrCode::encoding('UTF-8')->format('png')->size(300)->generate($url);
        $png    = base64_encode($qrcode);
        echo "<img src='data:image/png;base64," . $png . "'>";
    }
}
