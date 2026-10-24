<?php
$map = '';
for ($i = 0; $i < 128; $i++) {
    if ($i < 0x20 || $i == 0x7F) { $name = '.notdef'; }
    elseif ($i == 0x20) { $name = 'space'; }
    else { $name = chr($i); }
    $map .= sprintf("!%02X U+%04X %s\n", $i, $i, $name);
}

// Format: [isolated, final, initial, medial]
// Use same cp for initial and medial where no separate medial exists
$entries = [
    // Non-connecting (2 forms): alef, dal, dhal, reh, zain, waw, alef maksura, teh marbuta, hamza
    [0xFE8D, 0xFE8E, 0, 0], // alef
    [0xFEA9, 0xFEAA, 0, 0], // dal
    [0xFEAB, 0xFEAC, 0, 0], // dhal
    [0xFEAD, 0xFEAE, 0, 0], // reh
    [0xFEAF, 0xFEB0, 0, 0], // zain
    [0xFEED, 0xFEEE, 0, 0], // waw
    [0xFEEF, 0xFEF0, 0, 0], // alef maksura
    [0xFE93, 0xFE94, 0, 0], // teh marbuta
    [0xFE80, 0, 0, 0], // hamza (only isolated)

    // Connecting (4 forms, medial=initial)
    [0xFE90, 0xFE92, 0xFE91, 0xFE91], // beh (medial=initial)

    // Connecting (4 forms, distinct medial)
    [0xFE95, 0xFE97, 0xFE96, 0xFE98], // teh
    [0xFE99, 0xFE9B, 0xFE9A, 0xFE9C], // theh
    [0xFE9D, 0xFE9F, 0xFE9E, 0xFEA0], // jeem
    [0xFEA1, 0xFEA3, 0xFEA2, 0xFEA4], // hah
    [0xFEA5, 0xFEA7, 0xFEA6, 0xFEA8], // khah
    [0xFEB1, 0xFEB3, 0xFEB2, 0xFEB4], // seen
    [0xFEB5, 0xFEB7, 0xFEB6, 0xFEB8], // sheen
    [0xFEB9, 0xFEBB, 0xFEBA, 0xFEBC], // sad
    [0xFEBD, 0xFEBF, 0xFEBE, 0xFEC0], // dad
    [0xFEC1, 0xFEC3, 0xFEC2, 0xFEC4], // tah
    [0xFEC5, 0xFEC7, 0xFEC6, 0xFEC8], // zah
    [0xFEC9, 0xFECB, 0xFECA, 0xFECC], // ain
    [0xFECD, 0xFECF, 0xFECE, 0xFED0], // ghain
    [0xFED1, 0xFED3, 0xFED2, 0xFED4], // feh
    [0xFED5, 0xFED7, 0xFED6, 0xFED8], // qaf
    [0xFED9, 0xFEDB, 0xFEDA, 0xFEDC], // kaf
    [0xFEDD, 0xFEDF, 0xFEDE, 0xFEE0], // lam
    [0xFEE1, 0xFEE3, 0xFEE2, 0xFEE4], // meem
    [0xFEE5, 0xFEE7, 0xFEE6, 0xFEE8], // noon
    [0xFEE9, 0xFEEB, 0xFEEA, 0xFEEC], // heh
    [0xFEF1, 0xFEF3, 0xFEF2, 0xFEF4], // yeh

    // Extra
    [0x0640, 0, 0, 0], // tatweel
    [0xFEFB, 0xFEFC, 0, 0], // lam-alef
];

$idx = 0x80;
foreach ($entries as $forms) {
    foreach ($forms as $cp) {
        if ($cp > 0) {
            $map .= sprintf("!%02X U+%04X uni%04X\n", $idx, $cp, $cp);
        } else {
            $map .= sprintf("!%02X U+0000 .notdef\n", $idx);
        }
        $idx++;
        if ($idx > 0xFF) break 2;
    }
}

while ($idx < 256) {
    $map .= sprintf("!%02X U+0000 .notdef\n", $idx);
    $idx++;
}

file_put_contents(__DIR__ . '/arabic_shaped.map', $map);
echo "Map file created. Total lines: " . count(file(__DIR__ . '/arabic_shaped.map', FILE_SKIP_EMPTY_LINES)) . "\n";
echo "Last byte index: " . ($idx-1) . "\n";
