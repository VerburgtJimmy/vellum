<?php

declare(strict_types=1);

use Vellum\OpenApi\Samples\CurlGenerator;
use Vellum\OpenApi\Samples\JavaScriptGenerator;
use Vellum\OpenApi\Samples\PhpGenerator;
use Vellum\OpenApi\Samples\SampleRequest;

/**
 * A spec is often someone else's file, and its values end up in code a reader
 * copies and runs. Each of these values tries to end the string it is in.
 */
function hostileRequest(): SampleRequest
{
    return new SampleRequest(
        method: 'POST',
        url: 'https://api.test/pets/$(touch pwned)"`id`',
        headers: [
            'Authorization' => 'Bearer it\'s"$(id)\\',
            'X-Note' => "'); system('id'); //\\",
        ],
        query: [],
        body: ['name' => "O'Brien'; rm -rf ~ #", 'path\\' => 'C:\\'],
    );
}

it('writes a curl command whose arguments the shell reads back unchanged', function (): void {
    $request = hostileRequest();
    $sample = (new CurlGenerator)->generate($request);

    // Run the sample's own arguments through a real shell, with printf in
    // curl's place, in a directory where a command substitution would show.
    $directory = sys_get_temp_dir().'/vellum-curl-'.bin2hex(random_bytes(4));
    mkdir($directory);

    $script = preg_replace('/^curl /', "printf '%s\\n' ", $sample);
    exec('cd '.escapeshellarg($directory).' && sh -c '.escapeshellarg((string) $script).' 2>&1', $output, $status);

    $leftBehind = array_values(array_diff((array) scandir($directory), ['.', '..']));
    rmdir($directory);

    expect($status)->toBe(0)
        ->and($leftBehind)->toBe([])
        ->and(implode("\n", $output))->toBe(implode("\n", [
            '-X',
            'POST',
            $request->fullUrl(),
            '-H',
            'Authorization: '.$request->headers['Authorization'],
            '-H',
            'X-Note: '.$request->headers['X-Note'],
            '-d',
            $request->json(),
        ]));
});

it('keeps a method to its letters in a curl command', function (): void {
    $sample = (new CurlGenerator)->generate(new SampleRequest('GET; id', 'https://api.test'));

    expect($sample)->toBe("curl -X GETID 'https://api.test'");
});

it('writes PHP in which every spec value is one string and nothing else', function (): void {
    $request = hostileRequest();
    $sample = (new PhpGenerator)->generate($request);

    $strings = [];
    $calls = [];

    foreach (token_get_all('<?php '.$sample, TOKEN_PARSE) as $token) {
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $strings[] = strtr(substr($token[1], 1, -1), ['\\\\' => '\\', "\\'" => "'"]);
        }

        if ($token[0] === T_STRING) {
            $calls[] = $token[1];
        }
    }

    expect($strings)->toContain($request->fullUrl())
        ->toContain('it\'s"$(id)\\')
        ->toContain($request->headers['X-Note'])
        ->toContain($request->body['name'])
        ->toContain('path\\')
        ->toContain('C:\\')
        ->and($calls)->toBe(['Http', 'withToken', 'withHeaders', 'post']);
});

it('writes JavaScript in which a quote or backslash stays inside its string', function (): void {
    $sample = (new JavaScriptGenerator)->generate(new SampleRequest(
        method: 'GET',
        url: "https://api.test/'+alert(1)+'",
        headers: ['X-Note' => "a\\', b: evil(), c: '\nnext"],
    ));

    expect($sample)->toContain("fetch('https://api.test/\\'+alert(1)+\\'', {")
        ->toContain("'X-Note': 'a\\\\\\', b: evil(), c: \\'\\nnext',");
});

it('writes nothing curl would read as an option or as a file to send', function (): void {
    $sample = (new CurlGenerator)->generate(new SampleRequest(
        method: 'GET',
        url: '-o /tmp/x',
        headers: ['@/etc/passwd' => 'x', 'X-Good' => 'y'],
    ));

    expect($sample)->toBe("curl -X GET --url '-o /tmp/x' \\\n  -H 'X-Good: y'");
});
