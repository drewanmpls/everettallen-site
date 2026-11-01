<?php
// Minimal PDF writer (no libraries): Letter pages, Helvetica text with wrapping, rules, and JPEG images.
declare(strict_types=1);

class MiniPdf {
    private const W = 612, H = 792, M = 54;
    private const HELV = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const HELVB = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
    private array $pages = [];
    private string $buf = '';
    private float $y;
    private array $images = [];   // [name => [data, w, h, comps]]
    public function __construct() { $this->newPage(); }

    private function newPage(): void {
        if ($this->buf !== '' || $this->pages) $this->pages[] = $this->buf;
        $this->buf = ''; $this->y = self::H - self::M;
    }
    private function enc(string $s): string {
        $o = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return $o === false ? preg_replace('/[^\x20-\x7e]/', '?', $s) : $o;
    }
    private function esc(string $s): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->enc($s)); }
    public function width(string $s, float $size, bool $bold = false): float {
        $t = $bold ? self::HELVB : self::HELV; $w = 0;
        foreach (str_split($this->enc($s)) as $ch) { $c = ord($ch); $w += ($c >= 32 && $c <= 126) ? $t[$c - 32] : 556; }
        return $w * $size / 1000;
    }
    public function ensure(float $h): void { if ($this->y - $h < self::M) $this->newPage(); }
    public function space(float $h): void { $this->y -= $h; }
    public function line(string $s, float $size = 10, bool $bold = false, float $x = 0): void {
        $this->ensure($size * 1.35);
        $this->y -= $size;
        $this->buf .= sprintf("BT /%s %.1f Tf %.1f %.1f Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, self::M + $x, $this->y, $this->esc($s));
        $this->y -= $size * 0.35;
    }
    public function para(string $text, float $size = 10, bool $bold = false, float $x = 0, float $gap = 6): void {
        $maxW = self::W - 2 * self::M - $x;
        foreach (preg_split('/\R/', $text) as $paraLine) {
            $words = preg_split('/\s+/', trim($paraLine), -1, PREG_SPLIT_NO_EMPTY); $cur = '';
            if (!$words) { $this->space($size); continue; }
            foreach ($words as $w) {
                $try = $cur === '' ? $w : $cur . ' ' . $w;
                if ($this->width($try, $size, $bold) > $maxW && $cur !== '') { $this->line($cur, $size, $bold, $x); $cur = $w; } else $cur = $try;
            }
            if ($cur !== '') $this->line($cur, $size, $bold, $x);
        }
        $this->space($gap);
    }
    public function rule(): void { $this->ensure(8); $this->y -= 4; $this->buf .= sprintf("0.6 w %.1f %.1f m %.1f %.1f l S\n", self::M, $this->y, self::W - self::M, $this->y); $this->y -= 6; }
    // Two-column "Label: value" row
    public function field(string $label, string $value, float $size = 10): void {
        $this->ensure($size * 1.5);
        $this->y -= $size;
        $this->buf .= sprintf("BT /F2 %.1f Tf %.1f %.1f Td (%s) Tj ET\n", $size, self::M, $this->y, $this->esc($label));
        $this->buf .= sprintf("BT /F1 %.1f Tf %.1f %.1f Td (%s) Tj ET\n", $size, self::M + 130, $this->y, $this->esc($value));
        $this->y -= $size * 0.5;
    }
    public function jpeg(string $data, float $drawW): bool {
        $i = @getimagesizefromstring($data);
        if (!$i || $i[2] !== IMAGETYPE_JPEG) return false;
        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = [$data, $i[0], $i[1], $i['channels'] ?? 3];
        $drawH = $drawW * $i[1] / $i[0];
        $this->ensure($drawH + 4);
        $this->y -= $drawH;
        $this->buf .= sprintf("q %.1f 0 0 %.1f %.1f %.1f cm /%s Do Q\n", $drawW, $drawH, self::M, $this->y, $name);
        $this->y -= 4;
        return true;
    }
    public function output(): string {
        $this->pages[] = $this->buf; $pages = array_values(array_filter($this->pages, fn($p) => $p !== ''));
        $objs = []; $n = 0;
        $add = function (string $body) use (&$objs, &$n) { $objs[++$n] = $body; return $n; };
        $cat = $add(''); $pagesObj = $add(''); $f1 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'); $f2 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $imgRefs = [];
        foreach ($this->images as $name => [$data, $w, $h, $c]) {
            $cs = $c === 1 ? '/DeviceGray' : '/DeviceRGB';
            $imgRefs[$name] = $add("<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace $cs /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($data) . " >>\nstream\n" . $data . "\nendstream");
        }
        $xo = ''; foreach ($imgRefs as $name => $ref) $xo .= "/$name $ref 0 R ";
        $kids = [];
        foreach ($pages as $stream) {
            $ct = $add("<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream");
            $kids[] = $add("<< /Type /Page /Parent $pagesObj 0 R /MediaBox [0 0 612 792] /Contents $ct 0 R /Resources << /Font << /F1 $f1 0 R /F2 $f2 0 R >> /XObject << $xo>> >> >>");
        }
        $objs[$cat] = "<< /Type /Catalog /Pages $pagesObj 0 R >>";
        $objs[$pagesObj] = "<< /Type /Pages /Count " . count($kids) . " /Kids [" . implode(' ', array_map(fn($k) => "$k 0 R", $kids)) . "] >>";
        $out = "%PDF-1.4\n"; $off = [];
        foreach ($objs as $i => $body) { $off[$i] = strlen($out); $out .= "$i 0 obj\n$body\nendobj\n"; }
        $x = strlen($out); $out .= "xref\n0 " . ($n + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $n; $i++) $out .= sprintf("%010d 00000 n \n", $off[$i]);
        $out .= "trailer\n<< /Size " . ($n + 1) . " /Root $cat 0 R >>\nstartxref\n$x\n%%EOF";
        return $out;
    }
}
