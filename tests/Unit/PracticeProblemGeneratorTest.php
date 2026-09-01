<?php

namespace Tests\Unit;

use App\Support\PracticeProblemGenerator;
use PHPUnit\Framework\TestCase;

class PracticeProblemGeneratorTest extends TestCase
{
    public function test_every_new_curriculum_topic_has_a_working_problem_template(): void
    {
        $generator = new PracticeProblemGenerator();
        $keys = [];
        $expectedCounts = [1 => 15, 2 => 17, 3 => 22, 4 => 19, 5 => 20, 6 => 16];

        for ($grade = 1; $grade <= 6; $grade++) {
            $catalog = $generator->catalogForGrade($grade);

            $this->assertCount($expectedCounts[$grade], $catalog);
            foreach ($catalog as $index => $competency) {
                $keys[] = $competency['key'];
                $this->assertContains($competency['term'], ['First Term', 'Second Term', 'Third Term']);
                $this->assertContains($competency['strand'], [
                    'Number and Algebra',
                    'Measurement and Geometry',
                    'Data and Probability',
                ]);
                $this->assertNotSame('', $competency['summary']);

                for ($difficulty = 1; $difficulty <= 5; $difficulty++) {
                    $problem = $generator->generate(
                        $grade,
                        $competency['key'],
                        $difficulty,
                        ($grade * 10000) + ($difficulty * 1000) + $index
                    );

                    $this->assertNotSame('', $problem['prompt']);
                    $this->assertContains($problem['answer_type'], ['number', 'choice']);
                    $this->assertNotSame('', $problem['correct_answer']);
                    $this->assertNotEmpty($problem['hints']);
                    $this->assertNotSame('', $problem['explanation']);
                    $this->assertSame($difficulty, $problem['difficulty']);
                    $this->assertSame($competency['title'], $problem['title']);

                    if ($problem['answer_type'] === 'choice') {
                        $this->assertContains($problem['correct_answer'], $problem['options']);
                    }
                }
            }
        }

        $this->assertCount(109, array_unique($keys));
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

        foreach (['g1-shapes-2d', 'g3-line-relationships', 'g4-angles'] as $index => $key) {
            $grade = [1, 3, 4][$index];
            $problem = $generator->generate($grade, $key, 2, 42 + $index);

            $this->assertSame('choice', $problem['answer_type']);
            $this->assertContains($problem['correct_answer'], $problem['options']);
        }
    }

    public function test_problem_variations_stay_inside_the_published_curriculum_boundaries(): void
    {
        $generator = new PracticeProblemGenerator();

        for ($seed = 0; $seed < 25; $seed++) {
            $pictograph = $generator->generate(1, 'g1-pictographs', 5, $seed);
            $this->assertStringContainsString('each ★ represents 1 learner.', $pictograph['prompt']);

            $multiplication = $generator->generate(2, 'g2-equal-groups', 5, $seed);
            $matched = preg_match(
                '/There are (\d+) equal groups with (\d+) objects/',
                $multiplication['prompt'],
                $factors
            );
            $this->assertSame(1, $matched);
            $this->assertContains((int) $factors[1], [2, 3, 4, 5, 10]);
            $this->assertLessThanOrEqual(10, (int) $factors[2]);

            $largeOperation = $generator->generate(4, 'g4-multi-add', 5, $seed);
            $this->assertLessThanOrEqual(1000000, (int) $largeOperation['correct_answer']);
        }

        $translation = $generator->generate(3, 'g3-translation', 3, 2026);
        $this->assertSame('choice', $translation['answer_type']);
        $this->assertStringContainsString(' and ', $translation['prompt']);

        $volume = $generator->generate(5, 'g5-volume', 3, 2026);
        $this->assertStringContainsString('unit cubes', $volume['prompt']);
        $this->assertStringNotContainsString('cubic centimeters', $volume['prompt']);
    }
}
