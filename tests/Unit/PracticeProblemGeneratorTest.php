<?php

namespace Tests\Unit;

use App\Support\PracticeProblemGenerator;
use PHPUnit\Framework\TestCase;

class PracticeProblemGeneratorTest extends TestCase
{
    public function test_every_grade_has_four_working_autonomous_competencies(): void
    {
        $generator = new PracticeProblemGenerator();
        $keys = [];

        for ($grade = 1; $grade <= 6; $grade++) {
            $catalog = $generator->catalogForGrade($grade);

            $this->assertCount(4, $catalog);
            foreach ($catalog as $index => $competency) {
                $problem = $generator->generate($grade, $competency['key'], 3, 1000 + $index);

                $keys[] = $competency['key'];
                $this->assertNotSame('', $problem['prompt']);
                $this->assertContains($problem['answer_type'], ['number', 'choice']);
                $this->assertNotSame('', $problem['correct_answer']);
                $this->assertNotEmpty($problem['hints']);
                $this->assertNotSame('', $problem['explanation']);
                $this->assertSame(3, $problem['difficulty']);
                $this->assertSame($competency['title'], $problem['title']);
            }
        }

        $this->assertCount(24, array_unique($keys));
    }

    public function test_a_seed_reproduces_the_same_reviewed_problem(): void
    {
        $generator = new PracticeProblemGenerator();

        $first = $generator->generate(6, 'g6-percentages', 4, 8675309);
        $second = $generator->generate(6, 'g6-percentages', 4, 8675309);

        $this->assertSame($first, $second);
    }

    public function test_choice_questions_always_include_the_correct_answer(): void
    {
        $generator = new PracticeProblemGenerator();

        foreach (['g1-compare-100', 'g3-unit-fractions', 'g4-angles'] as $index => $key) {
            $grade = [1, 3, 4][$index];
            $problem = $generator->generate($grade, $key, 2, 42 + $index);

            $this->assertSame('choice', $problem['answer_type']);
            $this->assertContains($problem['correct_answer'], $problem['options']);
        }
    }
}
