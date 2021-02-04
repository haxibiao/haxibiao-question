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
        $query     = file_get_contents(__DIR__ . '/gql/Curation/curationQuery.graphql');
        $variables = [
            'limit'  => 10,
            'offset' => 0,
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group curation
     */
    public function testCreateCurationMutation()
    {
        $query     = file_get_contents(__DIR__ . '/gql/Curation/createCuration.graphql');
        $variables = [
            'question_id' => $this->question->id,
            'type'        => \random_int(1, 4),
            'content'     => 'test',
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group wrongAnswersQuery
     */
    public function testWrongAnswersQuery()
    {
        $query     = file_get_contents(__DIR__ . '/gql/Answer/WrongAnswersQuery.graphql');
        $variables = [
            'limit' => random_int(1, 4),
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

}
