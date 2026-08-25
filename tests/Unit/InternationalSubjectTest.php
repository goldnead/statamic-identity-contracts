<?php

use Goldnead\IdentityContracts\Support\EmailShape;

/**
 * A contact whose address carries an umlaut has to be recognised as a contact.
 *
 * It was not. The check was `filter_var($subject, FILTER_VALIDATE_EMAIL)`,
 * which predates RFC 6531 and refuses every non-ASCII character — so
 * `bärbel.öztürk@beispiel.de` fell straight past the contact branch to the
 * anonymous actor, and the bound ContactLocator was never asked. Every addon in
 * this family stores such addresses without complaint.
 *
 * Nothing failed visibly: the activity row was still written, it just had no
 * `contact_uuid` — the one column somebody later filters on to ask what this
 * person did.
 */
it('recognises an address a person actually has', function (string $email) {
    expect(EmailShape::plausible($email))->toBeTrue();
})->with([
    'umlauts in the local part' => 'bärbel.öztürk@beispiel.de',
    'the same one uppercased' => 'BÄRBEL.Öztürk@Beispiel.DE',
    'an international domain' => 'post@bäckerei.de',
    'both at once' => 'bärbel@bäckerei.de',
    'CJK' => '李明@beispiel.de',
    'the shortest plausible one' => 'a@b.de',
    'a plus address' => 'plus+tag@beispiel.de',
]);

it('still refuses what is not an address', function (mixed $wert) {
    expect(EmailShape::plausible($wert))->toBeFalse();
})->with([
    'no at sign' => 'kein-at',
    'two at signs' => 'zwei@@at.de',
    'nothing before the at' => '@beispiel.de',
    'a space' => 'mit leer@beispiel.de',
    'a bare hostname' => 'a@b',
    'angle brackets' => 'nutzer<fremd>@beispiel.de',
    'a header injection attempt' => "nutzer\r\nBcc: fremd@beispiel.de@beispiel.de",
    'the empty string' => '',
]);

it('refuses anything that is not a string without reaching for a string function', function () {
    // resolve() hands this whatever it was given, including objects and arrays.
    expect(EmailShape::plausible(null))->toBeFalse()
        ->and(EmailShape::plausible(42))->toBeFalse()
        ->and(EmailShape::plausible(['a@b.de']))->toBeFalse()
        ->and(EmailShape::plausible(new stdClass))->toBeFalse();
});
