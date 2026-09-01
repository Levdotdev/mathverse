<?php

namespace App\Support;

use InvalidArgumentException;

class PracticeProblemGenerator
{
    public function catalogForGrade(int $grade): array
    {
        return array_map($this->publicMetadata(...), PracticeCurriculum::forGrade($grade));
    }

    public function competency(int $grade, string $key): array
    {
        $item = PracticeCurriculum::find($grade, $key);
        if (!$item) {
            throw new InvalidArgumentException('Unknown practice competency.');
        }

        return $this->publicMetadata($item);
    }

    public function isVisibleCompetency(int $grade, string $key): bool
    {
        return PracticeCurriculum::isVisible($grade, $key);
    }

    public function generate(int $grade, string $competencyKey, int $difficulty, ?int $seed = null): array
    {
        $rawMeta = PracticeCurriculum::find($grade, $competencyKey);
        if (!$rawMeta) {
            throw new InvalidArgumentException('Unknown practice competency.');
        }

        $meta = $this->publicMetadata($rawMeta);
        $difficulty = max(1, min(5, $difficulty));
        $options = $rawMeta['options'] ?? [];

        $problem = match ($rawMeta['template']) {
            'addition' => $this->addition($difficulty, $seed, (int) ($options['limit'] ?? 100)),
            'subtraction' => $this->subtraction($difficulty, $seed, (int) ($options['limit'] ?? 100)),
            'comparison' => $this->comparison($difficulty, $seed, (int) ($options['limit'] ?? 100)),
            'place-value' => $this->placeValue($difficulty, $seed, (int) ($options['digits'] ?? 2)),
            'multiplication' => $this->multiplicationRange(
                $difficulty,
                $seed,
                (int) ($options['minimum'] ?? 1),
                (int) ($options['maximum'] ?? 10),
                (bool) ($options['groups'] ?? false),
                (array) ($options['factors'] ?? []),
                (int) ($options['counter_limit'] ?? 12)
            ),
            'division' => $this->divisionRange(
                $difficulty,
                $seed,
                (int) ($options['minimum'] ?? 1),
                (int) ($options['maximum'] ?? 10),
                (array) ($options['factors'] ?? []),
                (int) ($options['counter_limit'] ?? 12)
            ),
            'geometry' => $this->geometryProblem((string) $options['kind'], $difficulty, $seed),
            'number-sense' => $this->numberSenseProblem((string) $options['kind'], $difficulty, $seed, $options),
            'measurement' => $this->measurementProblem((string) $options['kind'], $difficulty, $seed),
            'data' => $this->dataProblem((string) $options['kind'], $difficulty, $seed, $options),
            'patterns' => $this->patternProblem((string) $options['kind'], $difficulty, $seed),
            'fractions' => $this->fractionProblem((string) $options['kind'], $difficulty, $seed),
            'money' => $this->moneyProblem((string) $options['kind'], $difficulty, $seed, (int) ($options['limit'] ?? 100)),
            'time' => $this->timeProblem((string) $options['kind'], $difficulty, $seed),
            'transformations' => $this->transformationProblem((string) $options['kind'], $difficulty, $seed),
            'arithmetic' => $this->arithmeticProblem((string) $options['kind'], $difficulty, $seed),
            'decimals' => $this->decimalProblem((string) $options['kind'], $difficulty, $seed, (int) ($options['places'] ?? 2)),
            'number-theory' => $this->numberTheoryProblem((string) $options['kind'], $difficulty, $seed),
            'ratios' => $this->ratioProblem((string) $options['kind'], $difficulty, $seed),
            'exponents' => $this->exponentProblem($difficulty, $seed),
            'circles' => $this->circleProblem((string) $options['kind'], $difficulty, $seed),
            'legacy-integers' => $this->integers($difficulty, $seed),
            'legacy-expressions' => $this->expressions($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown practice template.'),
        };

        return $meta + ['difficulty' => $difficulty] + $problem;
    }

    private function publicMetadata(array $item): array
    {
        unset($item['template'], $item['options']);

        return $item;
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
        $workingLimit = min($limit, max(20, (int) ceil($limit * ($difficulty + 1) / 6)));
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
        $denominators = [2, 3, 4, 5, 6, 8];
        $availableCount = min(count($denominators), 2 + $difficulty);
        $aIndex = $this->number(0, $availableCount - 1, 16, $seed);
        $bIndex = $this->number(0, $availableCount - 2, 17, $seed);
        if ($bIndex >= $aIndex) {
            $bIndex++;
        }
        $a = $denominators[$aIndex];
        $b = $denominators[$bIndex];
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

    private function multiplicationRange(
        int $difficulty,
        ?int $seed,
        int $minimum,
        int $maximum,
        bool $groups = false,
        array $factors = [],
        int $counterLimit = 12
    ): array {
        $minimum = max(1, min($minimum, $maximum));
        if ($factors !== []) {
            $available = array_values(array_filter($factors, fn ($factor): bool => is_int($factor) && $factor > 0));
            $a = $available !== []
                ? $available[$this->number(0, count($available) - 1, 50, $seed)]
                : $this->number($minimum, $maximum, 50, $seed);
        } else {
            $factorLimit = max($minimum, min($maximum, $minimum + $difficulty + 2));
            $a = $this->number($minimum, $factorLimit, 50, $seed);
        }
        $b = $this->number(2, min(max(2, $counterLimit), 4 + ($difficulty * 2)), 51, $seed);
        $answer = $a * $b;
        $prompt = $groups
            ? "There are {$a} equal groups with {$b} objects in each group. How many objects are there?"
            : "Calculate the product: {$a} × {$b} = ?";

        return $this->numberProblem(
            $prompt,
            $answer,
            [
                "Add {$b} repeatedly {$a} times.",
                "Use the multiplication fact {$a} × {$b}.",
            ],
            "{$a} groups of {$b} make {$a} × {$b} = {$answer}."
        );
    }

    private function divisionRange(
        int $difficulty,
        ?int $seed,
        int $minimum,
        int $maximum,
        array $factors = [],
        int $counterLimit = 12
    ): array {
        $minimum = max(1, min($minimum, $maximum));
        if ($factors !== []) {
            $available = array_values(array_filter($factors, fn ($factor): bool => is_int($factor) && $factor > 0));
            $divisor = $available !== []
                ? $available[$this->number(0, count($available) - 1, 52, $seed)]
                : $this->number($minimum, $maximum, 52, $seed);
        } else {
            $divisor = $this->number($minimum, max($minimum, min($maximum, $minimum + $difficulty + 2)), 52, $seed);
        }
        $quotient = $this->number(2, min(max(2, $counterLimit), 4 + ($difficulty * 2)), 53, $seed);
        $dividend = $divisor * $quotient;

        return $this->numberProblem(
            "Calculate the quotient: {$dividend} ÷ {$divisor} = ?",
            $quotient,
            [
                "Ask how many groups of {$divisor} fit into {$dividend}.",
                "Find the number that makes {$divisor} × ? = {$dividend}.",
            ],
            "{$divisor} × {$quotient} = {$dividend}, so {$dividend} ÷ {$divisor} = {$quotient}."
        );
    }

    private function geometryProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'shapes-2d' => $this->choiceProblem(
                'Which shape has 3 straight sides and 3 corners?',
                ['Triangle', 'Square', 'Rectangle'],
                'Triangle',
                ['Count the straight sides.', 'A three-sided polygon is a triangle.'],
                'A triangle has exactly 3 straight sides and 3 corners.'
            ),
            'circle-composites' => $this->numberProblem(
                'How many quarter circles fit together to make one whole circle?',
                4,
                ['A quarter is one of four equal parts.', 'Count four 1/4 pieces to make one whole.'],
                'Four quarter circles compose one whole circle.'
            ),
            'lines-surfaces' => $this->choiceProblem(
                'What kind of surface is the outside of a ball?',
                ['Flat', 'Curved', 'Straight'],
                'Curved',
                ['Imagine sliding your hand around the ball.', 'The surface bends instead of staying level.'],
                'The outside of a ball is a curved surface.'
            ),
            'perimeter' => $this->perimeter($difficulty, $seed),
            'area-rectangles' => $this->rectangleArea($difficulty, $seed),
            'line-basics' => $this->choiceProblem(
                'Which geometric object has one endpoint and continues forever in one direction?',
                ['Line segment', 'Ray', 'Line'],
                'Ray',
                ['A line segment has two endpoints.', 'A ray starts at one endpoint and continues in one direction.'],
                'A ray has one endpoint and extends forever in one direction.'
            ),
            'line-relationships' => $this->choiceProblem(
                'Two lines meet to form a 90° angle. How are the lines related?',
                ['Parallel', 'Perpendicular', 'Curved'],
                'Perpendicular',
                ['A 90° angle is a right angle.', 'Lines that meet at right angles are perpendicular.'],
                'The lines are perpendicular because they meet at a right angle.'
            ),
            'angles' => $this->angles($difficulty, $seed),
            'shape-properties' => $this->shapeProperties($seed),
            'composite-perimeter' => $this->compositePerimeter($difficulty, $seed),
            'symmetry' => $this->symmetryProblem($seed),
            'polygon-area' => $this->polygonArea($difficulty, $seed),
            'solid-figures' => $this->solidFigureProblem($seed),
            'surface-area' => $this->surfaceArea($difficulty, $seed),
            'volume-estimate' => $this->volumeWithUnitCubes($difficulty, $seed),
            'volume' => $this->volume($difficulty, $seed),
            'tessellation' => $this->choiceProblem(
                'Which regular shape can cover a flat surface with no gaps or overlaps?',
                ['Circle', 'Square', 'Regular pentagon'],
                'Square',
                ['Look for a shape whose copies meet exactly along every edge.', 'Four square corners meet to make 360°.'],
                'Squares tessellate because copies meet without gaps or overlaps.'
            ),
            'composite-area-perimeter' => $this->compositeArea($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown geometry practice kind.'),
        };
    }

    private function numberSenseProblem(
        string $kind,
        int $difficulty,
        ?int $seed,
        array $options
    ): array {
        $limit = (int) ($options['limit'] ?? 100);

        return match ($kind) {
            'whole-numbers' => $this->wholeNumberProblem($limit, $difficulty, $seed),
            'ordinals' => $this->ordinalProblem($limit, $seed),
            'odd-even' => $this->oddEvenProblem($limit, $difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown number-sense practice kind.'),
        };
    }

    private function measurementProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'length-nonstandard' => $this->nonStandardLength($difficulty, $seed),
            'length-metric' => $this->metricLength($seed),
            'mass' => $this->metricConversion('kg', 'g', 1000, $difficulty, $seed),
            'capacity' => $this->metricConversion('L', 'mL', 1000, $difficulty, $seed),
            'unit-conversion' => $this->mixedUnitConversion($difficulty, $seed),
            'volume-capacity' => $this->metricConversion('L', 'cm³', 1000, $difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown measurement practice kind.'),
        };
    }

    private function dataProblem(string $kind, int $difficulty, ?int $seed, array $options): array
    {
        return match ($kind) {
            'pictograph' => $this->pictographProblem($difficulty, $seed, (int) ($options['scale'] ?? 1)),
            'bar-graph' => $this->comparisonGraphProblem('bar graph', $difficulty, $seed),
            'likelihood' => $this->likelihoodProblem($seed),
            'line-graph' => $this->comparisonGraphProblem('line graph', $difficulty, $seed),
            'double-graph' => $this->doubleGraphProblem($difficulty, $seed),
            'theoretical-probability' => $this->theoreticalProbability($difficulty, $seed),
            'pie-graph' => $this->pieGraphProblem($seed),
            default => throw new InvalidArgumentException('Unknown data practice kind.'),
        };
    }

    private function patternProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'repeating' => $this->repeatingPattern($seed),
            'increasing-decreasing' => $this->numericPattern($difficulty, $seed, false),
            'combined' => $this->choiceProblem(
                'Continue the pattern: 1A, 1B, 2A, 2B, 3A, __',
                ['3B', '4A', '2B'],
                '3B',
                ['Each number appears twice.', 'The letters alternate A, B.'],
                'After 3A comes 3B because the number repeats while the letter changes.'
            ),
            'simple-rule' => $this->numericPattern($difficulty, $seed, true),
            default => throw new InvalidArgumentException('Unknown pattern practice kind.'),
        };
    }

    private function fractionProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'halves-quarters' => $this->choiceProblem(
                'Which fraction represents the larger part of the same whole?',
                ['1/4', '1/2', 'They are equal'],
                '1/2',
                ['Compare two equal parts with four equal parts.', 'Fewer equal pieces make each piece larger.'],
                'One half is larger than one quarter of the same whole.'
            ),
            'unit-similar' => $this->unitFractions($difficulty, $seed),
            'similar-add-sub' => $this->similarFractionOperation($difficulty, $seed),
            'compare-equivalent' => $this->equivalentFractions($difficulty, $seed),
            'dissimilar-add-sub' => $this->dissimilarFractionOperation($difficulty, $seed),
            'multiply' => $this->multiplyFractions($difficulty, $seed),
            'divide' => $this->divideFractions($difficulty, $seed),
            'mixed-operations' => $this->mixedFractionOperation($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown fraction practice kind.'),
        };
    }

    private function moneyProblem(string $kind, int $difficulty, ?int $seed, int $limit): array
    {
        $maximum = min($limit, max(20, (int) ceil($limit * ($difficulty + 1) / 6)));
        $first = $this->number(5, max(5, (int) floor($maximum / 2)), 54, $seed);
        $second = $this->number(5, max(5, $maximum - $first), 55, $seed);

        if ($kind === 'value') {
            $coin = [1, 5, 10, 20][$this->number(0, 3, 56, $seed)];
            $count = $this->number(2, max(2, min(8, (int) floor($limit / $coin))), 57, $seed);
            $answer = $coin * $count;

            return $this->numberProblem(
                "You have {$count} ₱{$coin} coins. What is their total value in pesos?",
                $answer,
                ["Each coin is worth ₱{$coin}.", "Multiply {$count} × {$coin}."],
                "{$count} × ₱{$coin} = ₱{$answer}."
            );
        }

        $addition = $this->number(0, 1, 176, $seed) === 1;
        if (!$addition && $second > $first) {
            [$first, $second] = [$second, $first];
        }
        $answer = $addition ? $first + $second : $first - $second;
        $prompt = $addition
            ? "A learner buys items costing ₱{$first} and ₱{$second}. How much is the total cost?"
            : "A learner has ₱{$first} and spends ₱{$second}. How much money remains?";

        return $this->numberProblem(
            $prompt,
            $answer,
            [$addition ? 'Add the two peso amounts.' : 'Subtract the amount spent.', 'Line up equal place values before calculating.'],
            "₱{$first} " . ($addition ? '+' : '−') . " ₱{$second} = ₱{$answer}."
        );
    }

    private function timeProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'clock-calendar' => $this->choiceProblem(
                'Which time means “quarter past 3”?',
                ['3:15', '3:30', '3:45'],
                '3:15',
                ['A quarter of an hour is 15 minutes.', '“Past 3” means after 3 o’clock.'],
                'Quarter past 3 is 3:15.'
            ),
            'elapsed' => $this->elapsedTime($difficulty, $seed),
            'time-systems' => $this->timeSystemConversion($seed),
            'time-zones' => $this->timeZoneProblem($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown time practice kind.'),
        };
    }

    private function transformationProblem(string $kind, int $difficulty, ?int $seed): array
    {
        if ($kind === 'combined') {
            $kinds = ['translation', 'reflection', 'rotation'];
            $kind = $kinds[$this->number(0, 2, 58, $seed)];
        }

        return match ($kind) {
            'turns' => $this->turnProblem($seed),
            'translation', 'translation-one-direction' => $this->translationProblem($difficulty, $seed),
            'translation-two-direction' => $this->twoDirectionTranslationProblem($difficulty, $seed),
            'reflection' => $this->reflectionProblem($difficulty, $seed),
            'rotation' => $this->rotationProblem($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown transformation practice kind.'),
        };
    }

    private function arithmeticProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'multiplication-properties' => $this->multiplicationProperty($seed),
            'multi-digit-multiplication' => $this->multiDigitMultiplication($difficulty, $seed),
            'estimate-products' => $this->estimateProduct($difficulty, $seed),
            'multi-digit-division' => $this->multiDigitDivision($difficulty, $seed),
            'estimate-quotients' => $this->estimateQuotient($difficulty, $seed),
            'large-add-subtract' => $this->largeAddSubtract($difficulty, $seed),
            'order-operations', 'gmdas' => $this->orderOfOperations($difficulty, $seed),
            'number-sentences' => $this->numberSentence($difficulty, $seed),
            'gmdas-fractions-decimals' => $this->fractionDecimalGmdas($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown arithmetic practice kind.'),
        };
    }

    private function decimalProblem(string $kind, int $difficulty, ?int $seed, int $places): array
    {
        return match ($kind) {
            'place-value' => $this->decimalPlaceValue($places, $seed),
            'fraction-relationship' => $this->decimalFractionConversion($places, $seed),
            'compare-convert' => $this->decimalCompareConvert($places, $seed),
            'add-subtract' => $this->decimalAddSubtract($places, $difficulty, $seed),
            'multiply' => $this->decimalMultiplication($places, $difficulty, $seed),
            'divide' => $this->decimalDivision($difficulty, $seed),
            'four-operations' => $this->fourDecimalOperations($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown decimal practice kind.'),
        };
    }

    private function numberTheoryProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'factors-multiples' => $this->factorMultipleProblem($difficulty, $seed),
            'divisibility' => $this->divisibilityProblem($difficulty, $seed),
            'prime-composite' => $this->primeCompositeProblem($difficulty, $seed),
            'gcf-lcm' => $this->gcfLcmProblem($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown number-theory practice kind.'),
        };
    }

    private function ratioProblem(string $kind, int $difficulty, ?int $seed): array
    {
        return match ($kind) {
            'ratios', 'proportions' => $this->ratios($difficulty, $seed),
            'percentages' => $this->percentages($difficulty, $seed),
            default => throw new InvalidArgumentException('Unknown ratio practice kind.'),
        };
    }

    private function exponentProblem(int $difficulty, ?int $seed): array
    {
        $base = $this->number(2, 3 + $difficulty, 59, $seed);
        $exponent = $this->number(2, min(4, 2 + (int) ceil($difficulty / 2)), 60, $seed);
        $power = $base ** $exponent;
        $addend = $this->number(1, 4 + $difficulty, 61, $seed);
        $answer = $power + $addend;

        return $this->numberProblem(
            "Apply GEMDAS: {$base}^{$exponent} + {$addend} = ?",
            $answer,
            ["Evaluate the exponent {$base}^{$exponent} first.", "Then add {$addend} to {$power}."],
            "{$base}^{$exponent} = {$power}, then {$power} + {$addend} = {$answer}."
        );
    }

    private function circleProblem(string $kind, int $difficulty, ?int $seed): array
    {
        $radius = $this->number(2, 3 + $difficulty, 62, $seed);
        $diameter = $radius * 2;

        return match ($kind) {
            'parts-circumference' => $this->numberProblem(
                "Use π = 3.14. What is the circumference of a circle with diameter {$diameter} cm?",
                $this->formatNumber(3.14 * $diameter),
                ['Use C = πd.', "Multiply 3.14 × {$diameter}."],
                'C = 3.14 × ' . $diameter . ' = ' . $this->formatNumber(3.14 * $diameter) . ' cm.'
            ),
            'area' => $this->numberProblem(
                "Use π = 3.14. What is the area of a circle with radius {$radius} cm?",
                $this->formatNumber(3.14 * $radius * $radius),
                ['Use A = πr².', "Square {$radius}, then multiply by 3.14."],
                'A = 3.14 × ' . $radius . '² = ' . $this->formatNumber(3.14 * $radius * $radius) . ' cm².'
            ),
            'composite-area' => $this->compositeCircleArea($radius),
            default => throw new InvalidArgumentException('Unknown circle practice kind.'),
        };
    }

    private function rectangleArea(int $difficulty, ?int $seed): array
    {
        $length = $this->number(2, 5 + ($difficulty * 2), 63, $seed);
        $width = $this->number(2, 4 + $difficulty, 64, $seed);
        $answer = $length * $width;

        return $this->numberProblem(
            "A rectangle is {$length} cm long and {$width} cm wide. What is its area in square centimeters?",
            $answer,
            ['Area counts the square units inside the rectangle.', "Multiply length × width: {$length} × {$width}."],
            "{$length} × {$width} = {$answer} square centimeters."
        );
    }

    private function shapeProperties(?int $seed): array
    {
        $variant = $this->number(0, 1, 65, $seed);

        return $variant === 0
            ? $this->choiceProblem(
                'Which quadrilateral has four equal sides and four right angles?',
                ['Square', 'Trapezoid', 'Parallelogram'],
                'Square',
                ['Check both the side lengths and angles.', 'A square has four congruent sides and four 90° angles.'],
                'A square has four equal sides and four right angles.'
            )
            : $this->choiceProblem(
                'Which triangle has exactly two equal sides?',
                ['Scalene', 'Isosceles', 'Equilateral'],
                'Isosceles',
                ['Classify the triangle by its side lengths.', '“Iso” indicates a matching pair.'],
                'An isosceles triangle has exactly two equal sides.'
            );
    }

    private function compositePerimeter(int $difficulty, ?int $seed): array
    {
        $sides = [
            $this->number(3, 5 + $difficulty, 66, $seed),
            $this->number(3, 6 + $difficulty, 67, $seed),
            $this->number(3, 7 + $difficulty, 68, $seed),
            $this->number(3, 8 + $difficulty, 69, $seed),
        ];
        $answer = array_sum($sides);

        return $this->numberProblem(
            'A quadrilateral has side lengths ' . implode(', ', $sides) . ' cm. What is its perimeter?',
            $answer,
            ['Perimeter is the total distance around the outside.', 'Add all four side lengths.'],
            implode(' + ', $sides) . " = {$answer} cm."
        );
    }

    private function symmetryProblem(?int $seed): array
    {
        $shapes = [
            ['Square', 4],
            ['Rectangle that is not a square', 2],
            ['Equilateral triangle', 3],
        ];
        [$shape, $answer] = $shapes[$this->number(0, 2, 70, $seed)];

        return $this->numberProblem(
            "How many lines of symmetry does a {$shape} have?",
            $answer,
            ['A symmetry line divides a figure into matching mirror halves.', 'Consider vertical, horizontal, and diagonal folds.'],
            "A {$shape} has {$answer} line" . ($answer === 1 ? '' : 's') . ' of symmetry.'
        );
    }

    private function polygonArea(int $difficulty, ?int $seed): array
    {
        $variant = $this->number(0, 2, 71, $seed);
        $base = $this->number(4, 6 + ($difficulty * 2), 72, $seed);
        $height = $this->number(2, 4 + $difficulty, 73, $seed);

        if ($variant === 0) {
            $answer = $base * $height;

            return $this->numberProblem(
                "A parallelogram has base {$base} cm and height {$height} cm. What is its area?",
                $answer,
                ['Use A = base × height.', "Multiply {$base} × {$height}."],
                "The area is {$base} × {$height} = {$answer} cm²."
            );
        }

        if ($variant === 1) {
            $evenBase = $base % 2 === 0 ? $base : $base + 1;
            $answer = ($evenBase * $height) / 2;

            return $this->numberProblem(
                "A triangle has base {$evenBase} cm and height {$height} cm. What is its area?",
                $answer,
                ['Use A = base × height ÷ 2.', "Multiply {$evenBase} × {$height}, then divide by 2."],
                "The area is ({$evenBase} × {$height}) ÷ 2 = {$answer} cm²."
            );
        }

        $top = $base;
        $bottom = $base + 2;
        $answer = (($top + $bottom) * $height) / 2;

        return $this->numberProblem(
            "A trapezoid has bases {$top} cm and {$bottom} cm and height {$height} cm. What is its area?",
            $answer,
            ['Add the parallel bases, multiply by the height, then divide by 2.', "Use A = ({$top} + {$bottom}) × {$height} ÷ 2."],
            "The area is ({$top} + {$bottom}) × {$height} ÷ 2 = {$answer} cm²."
        );
    }

    private function solidFigureProblem(?int $seed): array
    {
        $questions = [
            ['How many faces does a cube have?', ['6', '8', '12'], '6', 'A cube has 6 square faces.'],
            ['Which solid has two triangular bases and three rectangular faces?', ['Triangular prism', 'Square pyramid', 'Cube'], 'Triangular prism', 'A triangular prism has 2 triangular bases and 3 rectangular faces.'],
            ['Which net can fold into a cube?', ['Six connected squares', 'Four triangles', 'Two circles and one rectangle'], 'Six connected squares', 'A cube net is made of 6 connected squares.'],
        ];
        [$prompt, $options, $answer, $explanation] = $questions[$this->number(0, 2, 74, $seed)];

        return $this->choiceProblem(
            $prompt,
            $options,
            $answer,
            ['Think about the faces and bases of the solid.', 'Picture folding or unfolding the solid.'],
            $explanation
        );
    }

    private function surfaceArea(int $difficulty, ?int $seed): array
    {
        $length = $this->number(2, 4 + $difficulty, 75, $seed);
        $width = $this->number(2, 4 + $difficulty, 76, $seed);
        $height = $this->number(2, 4 + $difficulty, 77, $seed);
        $answer = 2 * (($length * $width) + ($length * $height) + ($width * $height));

        return $this->numberProblem(
            "A rectangular prism is {$length} cm by {$width} cm by {$height} cm. What is its surface area?",
            $answer,
            ['Find the areas of the three different face pairs.', 'Use 2(lw + lh + wh).'],
            "2[({$length}×{$width}) + ({$length}×{$height}) + ({$width}×{$height})] = {$answer} cm²."
        );
    }

    private function volumeWithUnitCubes(int $difficulty, ?int $seed): array
    {
        $columns = $this->number(2, 3 + $difficulty, 187, $seed);
        $rows = $this->number(2, 3 + $difficulty, 188, $seed);
        $layers = $this->number(2, 2 + $difficulty, 189, $seed);
        $answer = $columns * $rows * $layers;

        return $this->numberProblem(
            "A rectangular-prism model has {$layers} layers of unit cubes. Each layer has {$rows} rows of {$columns} cubes. How many unit cubes fill the model?",
            $answer,
            [
                "One layer contains {$rows} × {$columns} unit cubes.",
                "Multiply the cubes in one layer by {$layers} layers.",
            ],
            "{$rows} × {$columns} × {$layers} = {$answer} unit cubes."
        );
    }

    private function compositeArea(int $difficulty, ?int $seed): array
    {
        $length = $this->number(4, 7 + $difficulty, 78, $seed);
        $width = $this->number(3, 5 + $difficulty, 79, $seed);
        $triangleHeight = $this->number(2, 4 + $difficulty, 80, $seed);
        $rectangleArea = $length * $width;
        $triangleArea = ($length * $triangleHeight) / 2;
        $answer = $rectangleArea + $triangleArea;

        return $this->numberProblem(
            "A composite figure is a {$length} cm × {$width} cm rectangle plus a triangle with base {$length} cm and height {$triangleHeight} cm. What is its total area?",
            $answer,
            ['Find each simple area separately.', 'Add rectangle area lw and triangle area bh ÷ 2.'],
            "{$rectangleArea} + {$triangleArea} = {$answer} cm²."
        );
    }

    private function wholeNumberProblem(int $limit, int $difficulty, ?int $seed): array
    {
        $workingLimit = min($limit, max(20, (int) ceil($limit * ($difficulty + 1) / 6)));
        $variant = $this->number(0, 2, 81, $seed);

        if ($variant === 0) {
            $number = $this->number(1, max(1, $workingLimit - 1), 82, $seed);

            return $this->numberProblem(
                "What number is 1 more than {$number}?",
                $number + 1,
                ['Move one step forward when counting.', "Add 1 to {$number}."],
                "{$number} + 1 = " . ($number + 1) . '.'
            );
        }

        if ($variant === 1) {
            return $this->comparison($difficulty, $seed, $workingLimit);
        }

        $step = $limit >= 1000 ? 100 : ($limit >= 100 ? 10 : 2);
        $largestStart = max(0, (int) floor($workingLimit / $step) - 3);
        $start = $this->number(0, $largestStart, 83, $seed) * $step;

        return $this->numberProblem(
            "Continue skip-counting by {$step}: {$start}, " . ($start + $step) . ', ' . ($start + (2 * $step)) . ', __',
            $start + (3 * $step),
            ["Add {$step} each time.", 'Check that the difference between neighboring terms stays the same.'],
            'The next number is ' . ($start + (3 * $step)) . "."
        );
    }

    private function ordinalProblem(int $limit, ?int $seed): array
    {
        $position = $this->number(1, max(1, $limit), 84, $seed);
        $answer = $this->ordinal($position);
        $wrongPositions = $position >= $limit - 1
            ? [max(1, $position - 1), max(1, $position - 2)]
            : [min($limit, $position + 1), min($limit, $position + 2)];
        $options = [$answer, $this->ordinal($wrongPositions[0]), $this->ordinal($wrongPositions[1])];

        return $this->choiceProblem(
            "Which ordinal number names position {$position}?",
            $options,
            $answer,
            ['Ordinal numbers describe position rather than quantity.', 'Use the correct ending: st, nd, rd, or th.'],
            "Position {$position} is written {$answer}."
        );
    }

    private function oddEvenProblem(int $limit, int $difficulty, ?int $seed): array
    {
        $maximum = min($limit, max(20, (int) ceil($limit * ($difficulty + 1) / 6)));
        $value = $this->number(1, $maximum, 85, $seed);
        $answer = $value % 2 === 0 ? 'Even' : 'Odd';

        return $this->choiceProblem(
            "Is {$value} odd or even?",
            ['Even', 'Odd', 'Neither'],
            $answer,
            ['An even number can be split into pairs with none left over.', 'Check the final digit or divide by 2.'],
            "{$value} is {$answer} because division by 2 " . ($answer === 'Even' ? 'has no remainder.' : 'leaves a remainder of 1.')
        );
    }

    private function nonStandardLength(int $difficulty, ?int $seed): array
    {
        $first = $this->number(2, 5 + $difficulty, 86, $seed);
        $second = $this->number(2, 5 + $difficulty, 87, $seed);
        $answer = $first + $second;

        return $this->numberProblem(
            "A ribbon is {$first} paper clips long and another ribbon is {$second} paper clips long. What is their combined length in paper clips?",
            $answer,
            ['Use the same non-standard unit for both ribbons.', "Add {$first} and {$second}."],
            "{$first} + {$second} = {$answer} paper clips."
        );
    }

    private function metricLength(?int $seed): array
    {
        $variant = $this->number(0, 1, 88, $seed);

        return $variant === 0
            ? $this->choiceProblem(
                'Which unit is more appropriate for measuring the length of a pencil?',
                ['Centimeters', 'Meters', 'Kilometers'],
                'Centimeters',
                ['A pencil is much shorter than one meter.', 'Use centimeters for small everyday objects.'],
                'Centimeters are appropriate for the length of a pencil.'
            )
            : $this->choiceProblem(
                'Which unit is more appropriate for measuring the distance across a classroom?',
                ['Meters', 'Centimeters', 'Millimeters'],
                'Meters',
                ['A classroom spans several large steps.', 'Meters suit room-sized distances.'],
                'Meters are appropriate for the distance across a classroom.'
            );
    }

    private function metricConversion(
        string $largeUnit,
        string $smallUnit,
        int $factor,
        int $difficulty,
        ?int $seed
    ): array {
        $amount = $this->number(1, 2 + ($difficulty * 2), 89, $seed);
        $answer = $amount * $factor;

        return $this->numberProblem(
            "Convert {$amount} {$largeUnit} to {$smallUnit}.",
            $answer,
            ["One {$largeUnit} equals {$factor} {$smallUnit}.", "Multiply {$amount} × {$factor}."],
            "{$amount} {$largeUnit} = {$answer} {$smallUnit}."
        );
    }

    private function mixedUnitConversion(int $difficulty, ?int $seed): array
    {
        $conversions = [
            ['m', 'cm', 100],
            ['kg', 'g', 1000],
            ['L', 'mL', 1000],
            ['hours', 'minutes', 60],
        ];
        [$large, $small, $factor] = $conversions[$this->number(0, 3, 90, $seed)];

        return $this->metricConversion($large, $small, $factor, $difficulty, $seed);
    }

    private function pictographProblem(int $difficulty, ?int $seed, int $minimumScale): array
    {
        $scale = $minimumScale === 1
            ? 1
            : $this->number($minimumScale, $minimumScale + $difficulty, 91, $seed);
        $symbols = $this->number(2, 4 + $difficulty, 92, $seed);
        $answer = $scale * $symbols;

        return $this->numberProblem(
            "In a pictograph, each ★ represents {$scale} learner" . ($scale === 1 ? '' : 's') . ". A row has {$symbols} stars. How many learners does it represent?",
            $answer,
            ['Count the symbols in the row.', "Multiply {$symbols} symbols by the scale of {$scale}."],
            "{$symbols} × {$scale} = {$answer} learners."
        );
    }

    private function comparisonGraphProblem(string $graph, int $difficulty, ?int $seed): array
    {
        $first = $this->number(4, 8 + ($difficulty * 3), 93, $seed);
        $difference = $this->number(2, 3 + $difficulty, 94, $seed);
        $second = $first + $difference;

        return $this->numberProblem(
            "A {$graph} shows {$first} books read on Monday and {$second} on Tuesday. How many more books were read on Tuesday?",
            $difference,
            ['Identify the two values shown by the graph.', "Subtract {$first} from {$second}."],
            "{$second} − {$first} = {$difference} more books."
        );
    }

    private function likelihoodProblem(?int $seed): array
    {
        $red = $this->number(4, 8, 95, $seed);
        $blue = $this->number(1, max(1, $red - 1), 96, $seed);

        return $this->choiceProblem(
            "A bag contains {$red} red counters and {$blue} blue counters. Which color is more likely to be picked?",
            ['Red', 'Blue', 'Equally likely'],
            'Red',
            ['Compare the number of counters of each color.', 'More possible favorable outcomes means a greater chance.'],
            "Red is more likely because there are {$red} red counters but only {$blue} blue counters."
        );
    }

    private function doubleGraphProblem(int $difficulty, ?int $seed): array
    {
        $groupA = $this->number(5, 10 + ($difficulty * 3), 97, $seed);
        $difference = $this->number(1, 3 + $difficulty, 98, $seed);
        $groupB = $groupA + $difference;

        return $this->numberProblem(
            "A double bar graph shows Class A recycled {$groupA} bottles and Class B recycled {$groupB}. How many more did Class B recycle?",
            $difference,
            ['Read the bar for each class.', "Find the difference {$groupB} − {$groupA}."],
            "Class B recycled {$groupB} − {$groupA} = {$difference} more bottles."
        );
    }

    private function theoreticalProbability(int $difficulty, ?int $seed): array
    {
        $total = $this->number(4, 6 + $difficulty, 99, $seed);
        $favorable = $this->number(1, $total - 1, 100, $seed);

        return $this->numberProblem(
            "A spinner has {$total} equal sections and {$favorable} are green. What is the numerator of the probability of landing on green?",
            $favorable,
            ['Probability is favorable outcomes over all possible outcomes.', 'The numerator counts the green sections.'],
            "The probability is {$favorable}/{$total}, so its numerator is {$favorable}."
        );
    }

    private function pieGraphProblem(?int $seed): array
    {
        $percents = [25, 50, 75];
        $percent = $percents[$this->number(0, 2, 101, $seed)];
        $angle = (int) (3.6 * $percent);

        return $this->numberProblem(
            "A category is {$percent}% of a pie graph. What angle should its sector measure?",
            $angle,
            ['A full circle is 360°.', "Multiply {$percent}% by 360°, or multiply {$percent} by 3.6."],
            "{$percent}% of 360° is {$angle}°."
        );
    }

    private function repeatingPattern(?int $seed): array
    {
        $cycles = [
            [['2', '4', '2', '4', '2', '__'], ['4', '6', '2'], '4'],
            [['A', 'B', 'C', 'A', 'B', '__'], ['A', 'B', 'C'], 'C'],
            [['1', '3', '1', '3', '1', '__'], ['2', '3', '4'], '3'],
        ];
        [$sequence, $options, $answer] = $cycles[$this->number(0, 2, 102, $seed)];

        return $this->choiceProblem(
            'Complete the repeating pattern: ' . implode(', ', $sequence),
            $options,
            $answer,
            ['Find the smallest group that repeats.', 'Continue that same group without changing its order.'],
            "The repeating unit shows that the missing term is {$answer}."
        );
    }

    private function numericPattern(int $difficulty, ?int $seed, bool $askRule): array
    {
        $step = $this->number(2, 3 + $difficulty, 103, $seed);
        $start = $this->number(1, 8 + $difficulty, 104, $seed);
        $increasing = $this->number(0, 1, 105, $seed) === 1;
        if (!$increasing) {
            $start += $step * 4;
        }
        $terms = [$start];
        for ($index = 1; $index < 4; $index++) {
            $terms[] = $increasing ? $terms[$index - 1] + $step : $terms[$index - 1] - $step;
        }

        if ($askRule) {
            $answer = ($increasing ? 'Add ' : 'Subtract ') . $step;

            return $this->choiceProblem(
                'What rule generates this pattern: ' . implode(', ', $terms) . '?',
                [$answer, ($increasing ? 'Subtract ' : 'Add ') . $step, 'Multiply by 2'],
                $answer,
                ['Compare each term with the term before it.', 'Look for a constant difference.'],
                "The rule is “{$answer}” each time."
            );
        }

        $next = $increasing ? end($terms) + $step : end($terms) - $step;

        return $this->numberProblem(
            'Find the next term: ' . implode(', ', $terms) . ', __',
            $next,
            ['Find the constant difference between terms.', ($increasing ? 'Add ' : 'Subtract ') . "{$step} once more."],
            "The pattern continues with {$next}."
        );
    }

    private function similarFractionOperation(int $difficulty, ?int $seed): array
    {
        $denominator = $this->number(3, 7 + $difficulty, 106, $seed);
        $first = $this->number(1, $denominator - 1, 107, $seed);
        $second = $this->number(1, $denominator - 1, 108, $seed);
        $addition = $this->number(0, 1, 109, $seed) === 1;
        if (!$addition && $second > $first) {
            [$first, $second] = [$second, $first];
        }
        $answer = $addition ? $first + $second : $first - $second;
        $symbol = $addition ? '+' : '−';

        return $this->numberProblem(
            "Find the missing numerator: {$first}/{$denominator} {$symbol} {$second}/{$denominator} = ?/{$denominator}",
            $answer,
            ['The denominators already match, so keep the denominator.', ($addition ? 'Add' : 'Subtract') . ' the numerators.'],
            "{$first} {$symbol} {$second} = {$answer}, so the result is {$answer}/{$denominator}."
        );
    }

    private function dissimilarFractionOperation(int $difficulty, ?int $seed): array
    {
        $firstDenominator = [2, 3, 4][$this->number(0, 2, 110, $seed)];
        $scale = $this->number(2, min(4, 2 + $difficulty), 111, $seed);
        $commonDenominator = $firstDenominator * $scale;
        $firstNumerator = $this->number(1, $firstDenominator - 1, 112, $seed);
        $secondNumerator = $this->number(1, max(1, $commonDenominator - ($firstNumerator * $scale)), 113, $seed);
        $answer = ($firstNumerator * $scale) + $secondNumerator;

        return $this->numberProblem(
            "Find the numerator: {$firstNumerator}/{$firstDenominator} + {$secondNumerator}/{$commonDenominator} = ?/{$commonDenominator}",
            $answer,
            ["Rewrite {$firstNumerator}/{$firstDenominator} with denominator {$commonDenominator}.", 'Then add the numerators.'],
            "{$firstNumerator}/{$firstDenominator} = " . ($firstNumerator * $scale) . "/{$commonDenominator}; the numerator is {$answer}."
        );
    }

    private function multiplyFractions(int $difficulty, ?int $seed): array
    {
        $a = $this->number(1, 2 + $difficulty, 114, $seed);
        $b = $a + $this->number(1, 3, 115, $seed);
        $c = $this->number(1, 2 + $difficulty, 116, $seed);
        $d = $c + $this->number(1, 3, 117, $seed);
        $answer = $a * $c;

        return $this->numberProblem(
            "Before simplifying, what is the numerator of {$a}/{$b} × {$c}/{$d}?",
            $answer,
            ['Multiply the numerators together.', "Calculate {$a} × {$c}."],
            "The unreduced product is {$answer}/" . ($b * $d) . ", so the numerator is {$answer}."
        );
    }

    private function divideFractions(int $difficulty, ?int $seed): array
    {
        $a = $this->number(1, 2 + $difficulty, 118, $seed);
        $b = $a + $this->number(1, 3, 119, $seed);
        $c = $this->number(1, 2 + $difficulty, 120, $seed);
        $d = $c + $this->number(1, 3, 121, $seed);
        $answer = $a * $d;

        return $this->numberProblem(
            "After changing division to multiplication by the reciprocal, what is the numerator of {$a}/{$b} ÷ {$c}/{$d} before simplifying?",
            $answer,
            ["Use the reciprocal {$d}/{$c}.", "Multiply the numerators: {$a} × {$d}."],
            "{$a}/{$b} × {$d}/{$c} = {$answer}/" . ($b * $c) . ", so the numerator is {$answer}."
        );
    }

    private function mixedFractionOperation(int $difficulty, ?int $seed): array
    {
        $whole = $this->number(2, 3 + $difficulty, 122, $seed);
        $numerator = $this->number(1, 3 + $difficulty, 123, $seed);
        $denominator = $numerator + $this->number(1, 4, 124, $seed);
        $multiply = $this->number(0, 1, 177, $seed) === 1;
        $answer = $whole * ($multiply ? $numerator : $denominator);
        $prompt = $multiply
            ? "Find the numerator before simplifying: {$whole} × {$numerator}/{$denominator} = ?/{$denominator}"
            : "After multiplying by the reciprocal, what is the numerator before simplifying: {$whole} ÷ {$numerator}/{$denominator}?";

        return $this->numberProblem(
            $prompt,
            $answer,
            $multiply
                ? ['Write the whole number over 1.', "Multiply {$whole} × {$numerator}."]
                : ["Use the reciprocal {$denominator}/{$numerator}.", "Multiply {$whole} × {$denominator}."],
            $multiply
                ? "{$whole} × {$numerator}/{$denominator} = {$answer}/{$denominator}."
                : "{$whole}/1 × {$denominator}/{$numerator} = {$answer}/{$numerator}."
        );
    }

    private function elapsedTime(int $difficulty, ?int $seed): array
    {
        $start = $this->number(1, 10, 125, $seed);
        $hours = $this->number(1, min(4, 1 + $difficulty), 126, $seed);
        $end = (($start - 1 + $hours) % 12) + 1;

        return $this->numberProblem(
            "An activity starts at {$start}:00 and lasts {$hours} hour" . ($hours === 1 ? '' : 's') . '. At what hour does it end?',
            $end,
            ['Move forward one hour at a time.', "Add {$hours} hours to {$start}:00."],
            "The activity ends at {$end}:00."
        );
    }

    private function timeSystemConversion(?int $seed): array
    {
        $hour = $this->number(13, 22, 127, $seed);
        $answer = ($hour - 12) . ':00 p.m.';

        return $this->choiceProblem(
            "Convert {$hour}:00 from 24-hour time to 12-hour time.",
            [$answer, $hour . ':00 p.m.', ($hour - 12) . ':00 a.m.'],
            $answer,
            ['For an hour greater than 12, subtract 12.', 'Hours 13:00 through 23:59 are p.m.'],
            "{$hour}:00 is {$answer}."
        );
    }

    private function timeZoneProblem(int $difficulty, ?int $seed): array
    {
        $manila = $this->number(8, 18, 128, $seed);
        $difference = $this->number(1, min(6, 2 + $difficulty), 129, $seed);
        $ahead = $this->number(0, 1, 130, $seed) === 1;
        $local = ($manila + ($ahead ? $difference : -$difference) + 24) % 24;

        return $this->numberProblem(
            "It is {$manila}:00 in the Philippines. A city is {$difference} hour" . ($difference === 1 ? '' : 's') . ($ahead ? ' ahead' : ' behind') . '. What is the hour there in 24-hour time?',
            $local,
            [$ahead ? 'Add the time difference.' : 'Subtract the time difference.', 'Wrap past 23 back to 0 when needed.'],
            "The local time is {$local}:00."
        );
    }

    private function turnProblem(?int $seed): array
    {
        $directions = ['North', 'East', 'South', 'West'];
        $startIndex = $this->number(0, 3, 131, $seed);
        $quarterTurns = $this->number(1, 2, 132, $seed);
        $clockwise = $this->number(0, 1, 133, $seed) === 1;
        $offset = $clockwise ? $quarterTurns : -$quarterTurns;
        $answer = $directions[($startIndex + $offset + 8) % 4];
        $turnName = $quarterTurns === 2 ? 'half turn' : 'quarter turn';

        return $this->choiceProblem(
            "An arrow faces {$directions[$startIndex]}. After a {$turnName} " . ($clockwise ? 'clockwise' : 'counterclockwise') . ', which direction does it face?',
            $directions,
            $answer,
            ['A quarter turn is 90° and a half turn is 180°.', 'Trace the turn from the starting direction.'],
            "The arrow finishes facing {$answer}."
        );
    }

    private function translationProblem(int $difficulty, ?int $seed): array
    {
        $x = $this->number(0, 5 + $difficulty, 134, $seed);
        $steps = $this->number(1, 2 + $difficulty, 135, $seed);
        $answer = $x + $steps;

        return $this->numberProblem(
            "A marker starts in column {$x} and slides {$steps} columns to the right. Which column does it reach?",
            $answer,
            ['A slide changes position without turning the marker.', 'Moving right increases the column number.'],
            "{$x} + {$steps} = {$answer}."
        );
    }

    private function twoDirectionTranslationProblem(int $difficulty, ?int $seed): array
    {
        $x = $this->number(2, 5 + $difficulty, 181, $seed);
        $y = $this->number(2, 5 + $difficulty, 182, $seed);
        $horizontal = $this->number(1, 2 + $difficulty, 183, $seed);
        $vertical = $this->number(1, 2 + $difficulty, 184, $seed);
        $right = $this->number(0, 1, 185, $seed) === 1;
        $up = $this->number(0, 1, 186, $seed) === 1;
        $dx = $right ? $horizontal : -$horizontal;
        $dy = $up ? $vertical : -$vertical;
        $answer = '(' . ($x + $dx) . ', ' . ($y + $dy) . ')';

        return $this->choiceProblem(
            "A marker at grid position ({$x}, {$y}) slides {$horizontal} "
                . ($right ? 'right' : 'left')
                . " and {$vertical} "
                . ($up ? 'up' : 'down')
                . '. Where does it finish?',
            [
                $answer,
                '(' . ($x - $dx) . ', ' . ($y + $dy) . ')',
                '(' . ($x + $dx) . ', ' . ($y - $dy) . ')',
            ],
            $answer,
            ['Apply the horizontal slide first.', 'Then apply the vertical slide without changing the marker.'],
            "The marker finishes at {$answer}."
        );
    }

    private function reflectionProblem(int $difficulty, ?int $seed): array
    {
        $x = $this->number(1, 3 + $difficulty, 136, $seed);

        return $this->numberProblem(
            "A point has x-coordinate {$x}. After reflection across the y-axis, what is its new x-coordinate?",
            -$x,
            ['Reflection across the y-axis creates a mirror image.', 'The x-coordinate changes sign while its distance from the axis stays equal.'],
            "Reflecting across the y-axis changes x = {$x} to x = " . (-$x) . '.'
        );
    }

    private function rotationProblem(int $difficulty, ?int $seed): array
    {
        $distance = $this->number(1, 3 + $difficulty, 137, $seed);

        return $this->choiceProblem(
            "Point ({$distance}, 0) rotates 90° counterclockwise about the origin. Where does it land?",
            ["(0, {$distance})", "(0, -{$distance})", "(-{$distance}, 0)"],
            "(0, {$distance})",
            ['A 90° counterclockwise turn moves the positive x-axis to the positive y-axis.', 'The distance from the origin stays the same.'],
            "The point lands at (0, {$distance})."
        );
    }

    private function multiplicationProperty(?int $seed): array
    {
        $a = $this->number(2, 9, 138, $seed);
        $b = $this->number(2, 9, 139, $seed);

        return $this->choiceProblem(
            "Which expression has the same product as {$a} × {$b} by the commutative property?",
            ["{$b} × {$a}", "{$a} + {$b}", "{$a} × 1"],
            "{$b} × {$a}",
            ['The commutative property changes the order.', 'The operation and factors stay the same.'],
            "{$a} × {$b} = {$b} × {$a}."
        );
    }

    private function estimateProduct(int $difficulty, ?int $seed): array
    {
        $a = $this->number(12, 40 + ($difficulty * 20), 140, $seed);
        $b = $this->number(12, 40 + ($difficulty * 10), 141, $seed);
        $roundedA = (int) (round($a / 10) * 10);
        $roundedB = (int) (round($b / 10) * 10);
        $answer = $roundedA * $roundedB;

        return $this->numberProblem(
            "Estimate {$a} × {$b} by rounding both factors to the nearest ten.",
            $answer,
            ["{$a} rounds to {$roundedA} and {$b} rounds to {$roundedB}.", 'Multiply the rounded factors.'],
            "{$roundedA} × {$roundedB} = {$answer}."
        );
    }

    private function multiDigitDivision(int $difficulty, ?int $seed): array
    {
        $divisor = $this->number(2, $difficulty >= 4 ? 25 : 9, 142, $seed);
        $quotient = $this->number(12, 30 + ($difficulty * 30), 143, $seed);
        $dividend = $divisor * $quotient;

        return $this->numberProblem(
            "Calculate {$dividend} ÷ {$divisor}.",
            $quotient,
            ["Find how many groups of {$divisor} make {$dividend}.", 'Use multiplication to check the quotient.'],
            "{$divisor} × {$quotient} = {$dividend}, so the quotient is {$quotient}."
        );
    }

    private function estimateQuotient(int $difficulty, ?int $seed): array
    {
        $divisor = $this->number(2, 5 + $difficulty, 144, $seed) * 10;
        $quotient = $this->number(2, 5 + $difficulty, 145, $seed);
        $nearbyDividend = ($divisor * $quotient) + $this->number(-4, 4, 146, $seed);

        return $this->numberProblem(
            "Estimate {$nearbyDividend} ÷ {$divisor} using the nearby compatible multiple " . ($divisor * $quotient) . '.',
            $quotient,
            ["Replace {$nearbyDividend} with the nearby divisible number " . ($divisor * $quotient) . '.', "Then divide by {$divisor}."],
            ($divisor * $quotient) . " ÷ {$divisor} = {$quotient}."
        );
    }

    private function largeAddSubtract(int $difficulty, ?int $seed): array
    {
        $maximum = 200000 * $difficulty;
        $addition = $this->number(0, 1, 149, $seed) === 1;
        if ($addition) {
            $a = $this->number(1000, $maximum - 500, 147, $seed);
            $b = $this->number(500, $maximum - $a, 148, $seed);

            return $this->numberProblem(
                "Calculate {$a} + {$b}.",
                $a + $b,
                ['Align equal place values.', 'Add from right to left and regroup when needed.'],
                "{$a} + {$b} = " . ($a + $b) . '.'
            );
        }

        $a = $this->number(1000, $maximum, 147, $seed);
        $b = $this->number(500, max(500, $a - 1), 148, $seed);

        return $this->numberProblem(
            "Calculate {$a} − {$b}.",
            $a - $b,
            ['Align equal place values.', 'Subtract from right to left and regroup when needed.'],
            "{$a} − {$b} = " . ($a - $b) . '.'
        );
    }

    private function numberSentence(int $difficulty, ?int $seed): array
    {
        $a = $this->number(2, 8 + $difficulty, 150, $seed);
        $b = $this->number(2, 8 + $difficulty, 151, $seed);
        $c = $this->number(1, min($a + $b - 1, 6 + $difficulty), 152, $seed);
        $answer = $a + $b - $c;

        return $this->numberProblem(
            "Complete the equivalent number sentence: {$a} + {$b} = {$c} + ?",
            $answer,
            ["First find {$a} + {$b}.", "Subtract {$c} from that total."],
            "Both sides equal " . ($a + $b) . ", so the missing number is {$answer}."
        );
    }

    private function fractionDecimalGmdas(int $difficulty, ?int $seed): array
    {
        $factor = $this->number(2, 4 + $difficulty, 153, $seed);

        return $this->numberProblem(
            "Apply GMDAS: (1/2 + 0.5) × {$factor} = ?",
            $factor,
            ['Complete the parentheses first.', '1/2 equals 0.5, so the parentheses equal 1.'],
            "1/2 + 0.5 = 1, and 1 × {$factor} = {$factor}."
        );
    }

    private function decimalPlaceValue(int $places, ?int $seed): array
    {
        $places = max(1, min(4, $places));
        $scale = 10 ** $places;
        $digits = $this->number(1, $scale - 1, 154, $seed);
        $value = $this->formatNumber($digits / $scale, $places);
        $targetPlace = 10 ** $this->number(1, $places, 155, $seed);
        $digit = ((int) floor(($digits / $scale) * $targetPlace)) % 10;
        $answer = $this->formatNumber($digit / $targetPlace, $places);
        $placeName = match ($targetPlace) {
            10 => 'tenths',
            100 => 'hundredths',
            1000 => 'thousandths',
            default => 'ten-thousandths',
        };

        return $this->numberProblem(
            "What is the value of the digit {$digit} in the {$placeName} place of {$value}?",
            $answer,
            ["The {$placeName} place has value 1/{$targetPlace}.", "Multiply {$digit} by 1/{$targetPlace}."],
            "The digit {$digit} is worth {$answer}."
        );
    }

    private function decimalFractionConversion(int $places, ?int $seed): array
    {
        $denominator = $places >= 2 && $this->number(0, 1, 156, $seed) === 1 ? 100 : 10;
        $numerator = $this->number(1, $denominator - 1, 157, $seed);
        $answer = $this->formatNumber($numerator / $denominator, $places);

        return $this->numberProblem(
            "Convert {$numerator}/{$denominator} to a decimal.",
            $answer,
            ["The denominator {$denominator} names the decimal place.", "Divide {$numerator} by {$denominator}."],
            "{$numerator} ÷ {$denominator} = {$answer}."
        );
    }

    private function decimalCompareConvert(int $places, ?int $seed): array
    {
        $variant = $this->number(0, 2, 178, $seed);
        if ($variant === 0) {
            return $this->decimalFractionConversion($places, $seed);
        }

        $scale = 10 ** max(2, min(3, $places));
        $firstScaled = $this->number(1, $scale - 2, 179, $seed);
        $secondScaled = $this->number(1, $scale - 1, 180, $seed);
        if ($secondScaled === $firstScaled) {
            $secondScaled++;
        }
        $first = $this->formatNumber($firstScaled / $scale, $places);
        $second = $this->formatNumber($secondScaled / $scale, $places);

        if ($variant === 1) {
            $answer = $firstScaled < $secondScaled ? '<' : '>';

            return $this->choiceProblem(
                "Choose the symbol that makes this true: {$first} __ {$second}",
                ['<', '=', '>'],
                $answer,
                ['Align the decimal points.', 'Compare digits from left to right at equal place values.'],
                "{$first} {$answer} {$second}."
            );
        }

        $answer = $this->formatNumber(round($firstScaled / $scale, 1), 1);

        return $this->numberProblem(
            "Round {$first} to the nearest tenth.",
            $answer,
            ['Look at the hundredths digit.', 'Round the tenths digit up when the hundredths digit is 5 or more.'],
            "{$first} rounds to {$answer} to the nearest tenth."
        );
    }

    private function decimalAddSubtract(int $places, int $difficulty, ?int $seed): array
    {
        $scale = 10 ** max(1, min(4, $places));
        $aScaled = $this->number(10, 100 + ($difficulty * 100), 158, $seed);
        $bScaled = $this->number(1, $aScaled, 159, $seed);
        $a = $this->formatNumber($aScaled / $scale, $places);
        $b = $this->formatNumber($bScaled / $scale, $places);
        $addition = $this->number(0, 1, 160, $seed) === 1;
        $answer = $this->formatNumber(($addition ? $aScaled + $bScaled : $aScaled - $bScaled) / $scale, $places);
        $symbol = $addition ? '+' : '−';

        return $this->numberProblem(
            "Calculate {$a} {$symbol} {$b}.",
            $answer,
            ['Align the decimal points.', ($addition ? 'Add' : 'Subtract') . ' each place-value column.'],
            "{$a} {$symbol} {$b} = {$answer}."
        );
    }

    private function decimalMultiplication(int $places, int $difficulty, ?int $seed): array
    {
        $tenths = $this->number(11, 20 + ($difficulty * 8), 161, $seed);
        $factor = $this->number(2, 4 + $difficulty, 162, $seed);
        $a = $this->formatNumber($tenths / 10, min(2, $places));
        $answer = $this->formatNumber(($tenths * $factor) / 10, min(2, $places));

        return $this->numberProblem(
            "Calculate {$a} × {$factor}.",
            $answer,
            ['Multiply as whole numbers first.', 'Place the decimal so the product has the correct place value.'],
            "{$a} × {$factor} = {$answer}."
        );
    }

    private function decimalDivision(int $difficulty, ?int $seed): array
    {
        $divisor = $this->number(2, 4 + $difficulty, 163, $seed);
        $answerTenths = $this->number(2, 10 + ($difficulty * 2), 164, $seed);
        $dividendTenths = $divisor * $answerTenths;
        $dividend = $this->formatNumber($dividendTenths / 10, 2);
        $answer = $this->formatNumber($answerTenths / 10, 2);

        return $this->numberProblem(
            "Calculate {$dividend} ÷ {$divisor}.",
            $answer,
            ['Divide as with whole numbers while keeping decimal place value.', "Check by multiplying {$answer} × {$divisor}."],
            "{$dividend} ÷ {$divisor} = {$answer}."
        );
    }

    private function fourDecimalOperations(int $difficulty, ?int $seed): array
    {
        $kind = ['add', 'subtract', 'multiply', 'divide'][$this->number(0, 3, 165, $seed)];

        return match ($kind) {
            'add', 'subtract' => $this->decimalAddSubtract(4, $difficulty, $seed),
            'multiply' => $this->decimalMultiplication(2, $difficulty, $seed),
            default => $this->decimalDivision($difficulty, $seed),
        };
    }

    private function factorMultipleProblem(int $difficulty, ?int $seed): array
    {
        $a = $this->number(2, 5 + $difficulty, 166, $seed);
        $b = $this->number(2, 5 + $difficulty, 167, $seed);
        $product = $a * $b;

        return $this->choiceProblem(
            "Which number is a factor of {$product}?",
            [(string) $a, (string) ($a + 1), (string) ($product + 1)],
            (string) $a,
            ['A factor divides a number with no remainder.', "Check whether {$product} ÷ {$a} is a whole number."],
            "{$a} is a factor because {$a} × {$b} = {$product}."
        );
    }

    private function divisibilityProblem(int $difficulty, ?int $seed): array
    {
        $divisors = [2, 3, 4, 5, 6, 8, 9, 10, 11, 12];
        $divisor = $divisors[$this->number(0, min(count($divisors) - 1, 3 + $difficulty), 168, $seed)];
        $multiple = $this->number(2, 8 + $difficulty, 169, $seed);
        $value = $divisor * $multiple;

        return $this->choiceProblem(
            "Is {$value} divisible by {$divisor}?",
            ['Yes', 'No', 'Only with a remainder'],
            'Yes',
            ['Apply the divisibility rule for the divisor.', 'A number is divisible when the quotient is a whole number.'],
            "Yes. {$value} ÷ {$divisor} = {$multiple} with no remainder."
        );
    }

    private function primeCompositeProblem(int $difficulty, ?int $seed): array
    {
        $primes = [2, 3, 5, 7, 11, 13, 17, 19];
        $composites = [4, 6, 8, 9, 10, 12, 14, 15];
        $prime = $this->number(0, 1, 170, $seed) === 1;
        $pool = $prime ? $primes : $composites;
        $value = $pool[$this->number(0, min(count($pool) - 1, 2 + $difficulty), 171, $seed)];
        $answer = $prime ? 'Prime' : 'Composite';

        return $this->choiceProblem(
            "Is {$value} prime or composite?",
            ['Prime', 'Composite', 'Neither'],
            $answer,
            ['List the positive factors.', 'A prime has exactly two factors; a composite has more than two.'],
            "{$value} is {$answer}."
        );
    }

    private function gcfLcmProblem(int $difficulty, ?int $seed): array
    {
        $common = $this->number(2, 3 + $difficulty, 172, $seed);
        $aFactor = $this->number(2, 4 + $difficulty, 173, $seed);
        $bFactor = $this->number(2, 4 + $difficulty, 174, $seed);
        while ($this->gcd($aFactor, $bFactor) !== 1) {
            $bFactor++;
        }
        $a = $common * $aFactor;
        $b = $common * $bFactor;
        $askGcf = $this->number(0, 1, 175, $seed) === 1;
        $answer = $askGcf ? $this->gcd($a, $b) : (int) (($a * $b) / $this->gcd($a, $b));
        $label = $askGcf ? 'greatest common factor' : 'least common multiple';

        return $this->numberProblem(
            "Find the {$label} of {$a} and {$b}.",
            $answer,
            ['List factors or multiples systematically.', $askGcf ? 'Choose the largest shared factor.' : 'Choose the first shared positive multiple.'],
            "The {$label} of {$a} and {$b} is {$answer}."
        );
    }

    private function compositeCircleArea(int $radius): array
    {
        $squareSide = $radius * 2;
        $squareArea = $squareSide * $squareSide;
        $semicircleArea = (3.14 * $radius * $radius) / 2;
        $answer = $this->formatNumber($squareArea + $semicircleArea);

        return $this->numberProblem(
            "Use π = 3.14. A composite figure is a {$squareSide} cm square plus a semicircle of radius {$radius} cm. What is its total area?",
            $answer,
            ['Find the square area and half of the circle area.', 'Add the two non-overlapping areas.'],
            "{$squareArea} + " . $this->formatNumber($semicircleArea) . " = {$answer} cm²."
        );
    }

    private function formatNumber(float $value, int $places = 4): string
    {
        $formatted = rtrim(rtrim(number_format($value, $places, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    private function ordinal(int $number): string
    {
        $mod100 = $number % 100;
        $suffix = in_array($mod100, [11, 12, 13], true)
            ? 'th'
            : match ($number % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };

        return $number . $suffix;
    }

    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return abs($a);
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
