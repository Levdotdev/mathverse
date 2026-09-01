<?php

namespace App\Support;

use InvalidArgumentException;

class PracticeProblemGenerator
{
    /**
     * The first autonomous release intentionally uses reviewed, deterministic
     * templates. New variations change the values without trusting a browser
     * or a free-form generator to decide the correct answer.
     */
    private const CATALOG = [
        1 => [
            'g1-add-20' => ['title' => 'Addition Launchpad', 'world' => 'Number Kingdom', 'icon' => 'fa-plus', 'color' => '#22d3ee'],
            'g1-subtract-20' => ['title' => 'Subtraction Rescue', 'world' => 'Number Kingdom', 'icon' => 'fa-minus', 'color' => '#38bdf8'],
            'g1-compare-100' => ['title' => 'Number Scanner', 'world' => 'Comparison Cove', 'icon' => 'fa-scale-balanced', 'color' => '#a78bfa'],
            'g1-place-value' => ['title' => 'Place Value Towers', 'world' => 'Digit City', 'icon' => 'fa-city', 'color' => '#f59e0b'],
        ],
        2 => [
            'g2-add-100' => ['title' => 'Addition Engines', 'world' => 'Number Kingdom', 'icon' => 'fa-gears', 'color' => '#22d3ee'],
            'g2-subtract-100' => ['title' => 'Subtraction Shields', 'world' => 'Number Kingdom', 'icon' => 'fa-shield-halved', 'color' => '#38bdf8'],
            'g2-equal-groups' => ['title' => 'Equal Group Factory', 'world' => 'Multiplication Mine', 'icon' => 'fa-boxes-stacked', 'color' => '#34d399'],
            'g2-place-value' => ['title' => 'Hundreds Headquarters', 'world' => 'Digit City', 'icon' => 'fa-building', 'color' => '#f59e0b'],
        ],
        3 => [
            'g3-multiply' => ['title' => 'Times Table Thrusters', 'world' => 'Multiplication Mine', 'icon' => 'fa-xmark', 'color' => '#34d399'],
            'g3-divide' => ['title' => 'Division Dock', 'world' => 'Division Dock', 'icon' => 'fa-divide', 'color' => '#22d3ee'],
            'g3-unit-fractions' => ['title' => 'Fraction Forest', 'world' => 'Fraction Forest', 'icon' => 'fa-chart-pie', 'color' => '#a78bfa'],
            'g3-perimeter' => ['title' => 'Perimeter Patrol', 'world' => 'Geometry Galaxy', 'icon' => 'fa-draw-polygon', 'color' => '#f472b6'],
        ],
        4 => [
            'g4-multi-add' => ['title' => 'Multi-Digit Mission', 'world' => 'Number Kingdom', 'icon' => 'fa-plus', 'color' => '#22d3ee'],
            'g4-multiply' => ['title' => 'Product Power Plant', 'world' => 'Multiplication Mine', 'icon' => 'fa-bolt', 'color' => '#34d399'],
            'g4-equivalent-fractions' => ['title' => 'Equivalent Expedition', 'world' => 'Fraction Forest', 'icon' => 'fa-equals', 'color' => '#a78bfa'],
            'g4-angles' => ['title' => 'Angle Observatory', 'world' => 'Geometry Galaxy', 'icon' => 'fa-compass-drafting', 'color' => '#f472b6'],
        ],
        5 => [
            'g5-decimals' => ['title' => 'Decimal Drive', 'world' => 'Decimal District', 'icon' => 'fa-calculator', 'color' => '#22d3ee'],
            'g5-fraction-add' => ['title' => 'Fraction Fusion', 'world' => 'Fraction Forest', 'icon' => 'fa-chart-pie', 'color' => '#a78bfa'],
            'g5-volume' => ['title' => 'Volume Vault', 'world' => 'Measurement Mountain', 'icon' => 'fa-cube', 'color' => '#f59e0b'],
            'g5-order-operations' => ['title' => 'Operation Command', 'world' => 'Equation Station', 'icon' => 'fa-list-ol', 'color' => '#34d399'],
        ],
        6 => [
            'g6-ratios' => ['title' => 'Ratio Realm', 'world' => 'Ratio Realm', 'icon' => 'fa-code-compare', 'color' => '#a78bfa'],
            'g6-percentages' => ['title' => 'Percent Power', 'world' => 'Percent Planet', 'icon' => 'fa-percent', 'color' => '#22d3ee'],
            'g6-integers' => ['title' => 'Integer Icefield', 'world' => 'Integer Icefield', 'icon' => 'fa-temperature-half', 'color' => '#38bdf8'],
            'g6-expressions' => ['title' => 'Expression Engine', 'world' => 'Equation Station', 'icon' => 'fa-superscript', 'color' => '#34d399'],
        ],
    ];

    public function catalogForGrade(int $grade): array
    {
        if (!isset(self::CATALOG[$grade])) {
            throw new InvalidArgumentException('Practice is available for Grades 1 through 6.');
        }

        return array_map(
            fn (array $item, string $key): array => ['key' => $key] + $item,
            self::CATALOG[$grade],
            array_keys(self::CATALOG[$grade])
        );
    }

    public function competency(int $grade, string $key): array
    {
        $item = self::CATALOG[$grade][$key] ?? null;
        if (!$item) {
            throw new InvalidArgumentException('Unknown practice competency.');
        }

        return ['key' => $key] + $item;
    }

    public function generate(int $grade, string $competencyKey, int $difficulty, ?int $seed = null): array
    {
        $meta = $this->competency($grade, $competencyKey);
        $difficulty = max(1, min(5, $difficulty));

        $problem = match ($competencyKey) {
            'g1-add-20' => $this->addition($difficulty, $seed, 20),
            'g1-subtract-20' => $this->subtraction($difficulty, $seed, 20),
            'g1-compare-100' => $this->comparison($difficulty, $seed, 100),
            'g1-place-value' => $this->placeValue($difficulty, $seed, 2),
            'g2-add-100' => $this->addition($difficulty, $seed, 100),
            'g2-subtract-100' => $this->subtraction($difficulty, $seed, 100),
            'g2-equal-groups' => $this->multiplication($difficulty, $seed, 8, true),
            'g2-place-value' => $this->placeValue($difficulty, $seed, 3),
            'g3-multiply' => $this->multiplication($difficulty, $seed, 12),
            'g3-divide' => $this->division($difficulty, $seed),
            'g3-unit-fractions' => $this->unitFractions($difficulty, $seed),
            'g3-perimeter' => $this->perimeter($difficulty, $seed),
            'g4-multi-add' => $this->multiDigitAddition($difficulty, $seed),
            'g4-multiply' => $this->multiDigitMultiplication($difficulty, $seed),
            'g4-equivalent-fractions' => $this->equivalentFractions($difficulty, $seed),
            'g4-angles' => $this->angles($difficulty, $seed),
            'g5-decimals' => $this->decimalAddition($difficulty, $seed),
            'g5-fraction-add' => $this->fractionNumeratorAddition($difficulty, $seed),
            'g5-volume' => $this->volume($difficulty, $seed),
            'g5-order-operations' => $this->orderOfOperations($difficulty, $seed),
            'g6-ratios' => $this->ratios($difficulty, $seed),
            'g6-percentages' => $this->percentages($difficulty, $seed),
            'g6-integers' => $this->integers($difficulty, $seed),
            'g6-expressions' => $this->expressions($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown practice competency.'),
        };

        return $meta + ['difficulty' => $difficulty] + $problem;
    }

    private function addition(int $difficulty, ?int $seed, int $limit): array
    {
        $workingLimit = min($limit, max(10, (int) ceil($limit * ($difficulty + 1) / 6)));
        $a = $this->number(1, max(2, $workingLimit - 1), 1, $seed);
        $b = $this->number(1, max(1, $workingLimit - $a), 2, $seed);
        $answer = $a + $b;

        return $this->numberProblem(
            "Activate the sum: {$a} + {$b} = ?",
            $answer,
            [
                "Start at {$a} and count forward {$b} steps.",
                "Break {$b} into smaller parts, then add each part to {$a}.",
            ],
            "{$a} + {$b} = {$answer}."
        );
    }

    private function subtraction(int $difficulty, ?int $seed, int $limit): array
    {
        $workingLimit = min($limit, max(10, (int) ceil($limit * ($difficulty + 1) / 6)));
        $a = $this->number(2, $workingLimit, 3, $seed);
        $b = $this->number(1, $a - 1, 4, $seed);
        $answer = $a - $b;

        return $this->numberProblem(
            "Restore the difference: {$a} − {$b} = ?",
            $answer,
            [
                "Begin with {$a} and count backward {$b} steps.",
                "You can check your result by adding {$b} back to it.",
            ],
            "{$a} − {$b} = {$answer}. Adding {$answer} + {$b} returns {$a}."
        );
    }

    private function comparison(int $difficulty, ?int $seed, int $limit): array
    {
        $workingLimit = min($limit, max(20, $difficulty * 20));
        $a = $this->number(0, $workingLimit, 5, $seed);
        $b = $this->number(0, $workingLimit, 6, $seed);
        if ($this->number(0, 4, 7, $seed) !== 0 && $a === $b) {
            $b = ($b + 1) % ($workingLimit + 1);
        }
        $answer = $a < $b ? '<' : ($a > $b ? '>' : '=');

        return $this->choiceProblem(
            "Choose the symbol that makes this true: {$a} __ {$b}",
            ['<', '=', '>'],
            $answer,
            [
                'Compare the place values from left to right.',
                'The open side of the greater-than symbol faces the larger number.',
            ],
            "{$a} {$answer} {$b} is the true comparison."
        );
    }

    private function placeValue(int $difficulty, ?int $seed, int $digits): array
    {
        $ones = $this->number(1, 9, 8, $seed);
        $tens = $this->number(1, 9, 9, $seed);
        $hundreds = $digits >= 3 ? $this->number(1, 9, 10, $seed) : 0;
        $number = ($hundreds * 100) + ($tens * 10) + $ones;
        $places = $digits >= 3 ? [1, 10, 100] : [1, 10];
        $place = $places[$this->number(0, count($places) - 1, 11, $seed)];
        $digit = (int) floor($number / $place) % 10;
        $placeName = match ($place) { 100 => 'hundreds', 10 => 'tens', default => 'ones' };
        $answer = $digit * $place;

        return $this->numberProblem(
            "What is the value of the digit {$digit} in {$number}?",
            $answer,
            [
                "The digit {$digit} is in the {$placeName} place.",
                "Multiply {$digit} by {$place}.",
            ],
            "The digit {$digit} is worth {$answer} because it is in the {$placeName} place."
        );
    }

    private function multiplication(int $difficulty, ?int $seed, int $maximum, bool $groups = false): array
    {
        $factorLimit = min($maximum, 3 + ($difficulty * 2));
        $a = $this->number(2, max(2, $factorLimit), 12, $seed);
        $b = $this->number(2, max(3, $factorLimit), 13, $seed);
        $answer = $a * $b;
        $prompt = $groups
            ? "There are {$a} groups with {$b} objects in each group. How many objects are there?"
            : "Power the product: {$a} × {$b} = ?";

        return $this->numberProblem(
            $prompt,
            $answer,
            [
                "Add {$b} repeatedly {$a} times.",
                "You can also think of {$a} rows with {$b} objects in every row.",
            ],
            "{$a} groups of {$b} make {$a} × {$b} = {$answer}."
        );
    }

    private function division(int $difficulty, ?int $seed): array
    {
        $divisor = $this->number(2, min(12, 3 + ($difficulty * 2)), 14, $seed);
        $quotient = $this->number(2, min(12, 4 + ($difficulty * 2)), 15, $seed);
        $dividend = $divisor * $quotient;

        return $this->numberProblem(
            "Dock the quotient: {$dividend} ÷ {$divisor} = ?",
            $quotient,
            [
                "Ask how many groups of {$divisor} fit into {$dividend}.",
                "Find the number that makes {$divisor} × ? = {$dividend}.",
            ],
            "{$divisor} × {$quotient} = {$dividend}, so {$dividend} ÷ {$divisor} = {$quotient}."
        );
    }

    private function unitFractions(int $difficulty, ?int $seed): array
    {
        $a = $this->number(2, 5 + $difficulty, 16, $seed);
        $b = $this->number(2, 6 + $difficulty, 17, $seed);
        if ($a === $b) {
            $b++;
        }
        $first = "1/{$a}";
        $second = "1/{$b}";
        $answer = $a < $b ? $first : $second;

        return $this->choiceProblem(
            "Which unit fraction is greater: {$first} or {$second}?",
            [$first, $second, 'They are equal'],
            $answer,
            [
                'For unit fractions, imagine one equal piece of the same whole.',
                'A smaller denominator creates a larger piece.',
            ],
            "{$answer} is greater because its denominator is smaller, so each piece is larger."
        );
    }

    private function perimeter(int $difficulty, ?int $seed): array
    {
        $length = $this->number(3, 6 + ($difficulty * 3), 18, $seed);
        $width = $this->number(2, 4 + ($difficulty * 2), 19, $seed);
        $answer = 2 * ($length + $width);

        return $this->numberProblem(
            "A rectangle is {$length} m long and {$width} m wide. What is its perimeter in meters?",
            $answer,
            [
                'Perimeter is the distance around all four sides.',
                "Use 2 × ({$length} + {$width}).",
            ],
            "2 × ({$length} + {$width}) = 2 × " . ($length + $width) . " = {$answer} meters."
        );
    }

    private function multiDigitAddition(int $difficulty, ?int $seed): array
    {
        $maximum = 200 + ($difficulty * 800);
        $a = $this->number(100, $maximum, 20, $seed);
        $b = $this->number(100, $maximum, 21, $seed);
        $answer = $a + $b;

        return $this->numberProblem(
            "Complete the navigation total: {$a} + {$b} = ?",
            $answer,
            [
                'Line up the ones, tens, hundreds, and thousands places.',
                'Add from right to left and regroup whenever a column reaches 10.',
            ],
            "Adding the aligned place values gives {$a} + {$b} = {$answer}."
        );
    }

    private function multiDigitMultiplication(int $difficulty, ?int $seed): array
    {
        $a = $this->number(12, 30 + ($difficulty * 25), 22, $seed);
        $bMaximum = $difficulty >= 4 ? 25 : 9;
        $b = $this->number(2, $bMaximum, 23, $seed);
        $answer = $a * $b;

        return $this->numberProblem(
            "Charge the power cell: {$a} × {$b} = ?",
            $answer,
            [
                "Break {$a} into place-value parts before multiplying by {$b}.",
                'Add the partial products to get the final product.',
            ],
            "The partial products combine to make {$a} × {$b} = {$answer}."
        );
    }

    private function equivalentFractions(int $difficulty, ?int $seed): array
    {
        $numerator = $this->number(1, 4 + $difficulty, 24, $seed);
        $denominator = $numerator + $this->number(1, 5 + $difficulty, 25, $seed);
        $scale = $this->number(2, 2 + $difficulty, 26, $seed);
        $scaledNumerator = $numerator * $scale;
        $answer = $denominator * $scale;

        return $this->numberProblem(
            "Complete the equivalent fraction: {$numerator}/{$denominator} = {$scaledNumerator}/?",
            $answer,
            [
                "{$numerator} was multiplied by {$scale} to make {$scaledNumerator}.",
                "Multiply the denominator {$denominator} by the same number.",
            ],
            "Multiplying both parts by {$scale} gives {$scaledNumerator}/{$answer}."
        );
    }

    private function angles(int $difficulty, ?int $seed): array
    {
        $types = ['acute', 'right', 'obtuse'];
        $type = $types[$this->number(0, count($types) - 1, 27, $seed)];
        $degrees = match ($type) {
            'acute' => $this->number(15, 85, 28, $seed),
            'right' => 90,
            default => $this->number(95, min(175, 120 + ($difficulty * 10)), 29, $seed),
        };

        return $this->choiceProblem(
            "Classify an angle that measures {$degrees}°.",
            ['Acute', 'Right', 'Obtuse'],
            ucfirst($type),
            [
                'Compare the angle measure with 90°.',
                'Acute is below 90°, right is exactly 90°, and obtuse is between 90° and 180°.',
            ],
            "An angle measuring {$degrees}° is {$type}."
        );
    }

    private function decimalAddition(int $difficulty, ?int $seed): array
    {
        $aCents = $this->number(10, 100 + ($difficulty * 180), 30, $seed);
        $bCents = $this->number(10, 100 + ($difficulty * 180), 31, $seed);
        $answerCents = $aCents + $bCents;
        $a = $this->decimal($aCents);
        $b = $this->decimal($bCents);
        $answer = $this->decimal($answerCents);

        return $this->numberProblem(
            "Align the decimals: {$a} + {$b} = ?",
            $answer,
            [
                'Write the numbers so their decimal points line up.',
                'Add each place-value column from right to left.',
            ],
            "With the decimal points aligned, {$a} + {$b} = {$answer}."
        );
    }

    private function fractionNumeratorAddition(int $difficulty, ?int $seed): array
    {
        $denominator = $this->number(3, 7 + $difficulty, 32, $seed);
        $a = $this->number(1, $denominator - 1, 33, $seed);
        $b = $this->number(1, $denominator - 1, 34, $seed);
        $answer = $a + $b;

        return $this->numberProblem(
            "Find the missing numerator: {$a}/{$denominator} + {$b}/{$denominator} = ?/{$denominator}",
            $answer,
            [
                'The denominators already match, so keep that denominator.',
                "Add only the numerators: {$a} + {$b}.",
            ],
            "{$a} + {$b} = {$answer}, so the sum is {$answer}/{$denominator}."
        );
    }

    private function volume(int $difficulty, ?int $seed): array
    {
        $length = $this->number(2, 3 + ($difficulty * 2), 35, $seed);
        $width = $this->number(2, 3 + $difficulty, 36, $seed);
        $height = $this->number(2, 3 + $difficulty, 37, $seed);
        $answer = $length * $width * $height;

        return $this->numberProblem(
            "A rectangular prism measures {$length} cm × {$width} cm × {$height} cm. What is its volume in cubic centimeters?",
            $answer,
            [
                'Volume of a rectangular prism is length × width × height.',
                "Multiply {$length} × {$width}, then multiply that result by {$height}.",
            ],
            "{$length} × {$width} × {$height} = {$answer} cubic centimeters."
        );
    }

    private function orderOfOperations(int $difficulty, ?int $seed): array
    {
        $a = $this->number(2, 5 + $difficulty, 38, $seed);
        $b = $this->number(2, 5 + $difficulty, 39, $seed);
        $c = $this->number(2, 3 + $difficulty, 40, $seed);
        $answer = ($a + $b) * $c;

        return $this->numberProblem(
            "Follow the command order: ({$a} + {$b}) × {$c} = ?",
            $answer,
            [
                'Complete the operation inside the parentheses first.',
                'Multiply the result inside the parentheses by the final number.',
            ],
            "First, {$a} + {$b} = " . ($a + $b) . ". Then " . ($a + $b) . " × {$c} = {$answer}."
        );
    }

    private function ratios(int $difficulty, ?int $seed): array
    {
        $first = $this->number(1, 3 + $difficulty, 41, $seed);
        $second = $this->number(2, 5 + $difficulty, 42, $seed);
        $scale = $this->number(2, 3 + $difficulty, 43, $seed);
        $scaledFirst = $first * $scale;
        $answer = $second * $scale;

        return $this->numberProblem(
            "A ship uses {$first} blue crystals for every {$second} purple crystals. If it uses {$scaledFirst} blue crystals, how many purple crystals are needed?",
            $answer,
            [
                "Find what multiplied {$first} to make {$scaledFirst}.",
                "Multiply {$second} by that same scale factor, {$scale}.",
            ],
            "The ratio was scaled by {$scale}, so {$second} × {$scale} = {$answer} purple crystals."
        );
    }

    private function percentages(int $difficulty, ?int $seed): array
    {
        $percents = [10, 20, 25, 50, 75];
        $available = array_slice($percents, 0, min(count($percents), 2 + $difficulty));
        $percent = $available[$this->number(0, count($available) - 1, 44, $seed)];
        $whole = $this->number(1, 5 + ($difficulty * 2), 45, $seed) * 20;
        $answer = (int) (($percent / 100) * $whole);

        return $this->numberProblem(
            "Calculate {$percent}% of {$whole}.",
            $answer,
            [
                "Convert {$percent}% to {$percent}/100.",
                "Multiply {$whole} by {$percent}, then divide by 100.",
            ],
            "{$percent}% of {$whole} is ({$percent} × {$whole}) ÷ 100 = {$answer}."
        );
    }

    private function integers(int $difficulty, ?int $seed): array
    {
        $range = 5 + ($difficulty * 5);
        $a = $this->number(-$range, $range, 46, $seed);
        $b = $this->number(-$range, $range, 47, $seed);
        $answer = $a + $b;
        $bDisplay = $b < 0 ? "({$b})" : (string) $b;

        return $this->numberProblem(
            "Stabilize the temperature: {$a} + {$bDisplay} = ?",
            $answer,
            [
                'On a number line, positive values move right and negative values move left.',
                'If the signs differ, subtract their absolute values and keep the sign of the larger absolute value.',
            ],
            "Moving {$b} units from {$a} lands on {$answer}."
        );
    }

    private function expressions(int $difficulty, ?int $seed): array
    {
        $x = $this->number(1, 8 + ($difficulty * 4), 48, $seed);
        $addend = $this->number(2, 8 + ($difficulty * 3), 49, $seed);
        $total = $x + $addend;

        return $this->numberProblem(
            "Solve for x: x + {$addend} = {$total}",
            $x,
            [
                "Undo adding {$addend} by subtracting {$addend} from both sides.",
                "Calculate {$total} − {$addend}.",
            ],
            "x = {$total} − {$addend} = {$x}. Checking: {$x} + {$addend} = {$total}."
        );
    }

    private function numberProblem(string $prompt, int|float|string $answer, array $hints, string $explanation): array
    {
        return [
            'prompt' => $prompt,
            'answer_type' => 'number',
            'options' => [],
            'correct_answer' => (string) $answer,
            'hints' => array_values($hints),
            'explanation' => $explanation,
        ];
    }

    private function choiceProblem(
        string $prompt,
        array $options,
        string $answer,
        array $hints,
        string $explanation
    ): array {
        return [
            'prompt' => $prompt,
            'answer_type' => 'choice',
            'options' => array_values($options),
            'correct_answer' => $answer,
            'hints' => array_values($hints),
            'explanation' => $explanation,
        ];
    }

    private function decimal(int $cents): string
    {
        return rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');
    }

    private function number(int $minimum, int $maximum, int $salt, ?int $seed): int
    {
        if ($maximum < $minimum) {
            $maximum = $minimum;
        }

        if ($seed === null) {
            return random_int($minimum, $maximum);
        }

        $unsigned = (int) sprintf('%u', crc32("{$seed}:{$salt}"));

        return $minimum + ($unsigned % (($maximum - $minimum) + 1));
    }
}
