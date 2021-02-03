<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;

use App\User;
use App\Question;
use Haxibiao\Breeze\GraphQLTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CurationTest extends GraphQLTestCase
{
    use DatabaseTransactions;
    protected $user;
    protected $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory([
            'api_token' => str_random(60),
            'account'   => rand(10000000000, 99999999999),
        ])->create();
        $this->question = Question::factory(['user_id' => $this->user->id])->create();
    }

    /**
     * @group curation
     */
    public function testcurationQuery()
    {//$user->curations()
        $query     = file_get_contents(__DIR__ . '/gql/curation/curationQuery.gql');
        $variables = [
            'limit'  => 10,
            'offset' => 0,
        ];
        $this->runGQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group curation
     */
    public function testCreateCurationMutation()
    {
        $query     = file_get_contents(__DIR__ . '/gql/curation/createCuration.gql');
        $variables = [
            'question_id' => $this->question->id,
            'type'        => \random_int(1, 4),
            'content'     => 'test',
        ];
        $this->runGQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group wrongAnswersQuery
     */
    public function testWrongAnswersQuery()
    {
        $query     = file_get_contents(__DIR__ . '/gql/answer/WrongAnswersQuery.gql');
        $variables = [
            'limit' => random_int(1, 4),
        ];
        $this->runGQL($query, $variables, $this->getHeaders($this->user));
    }

}
