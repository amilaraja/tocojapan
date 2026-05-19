<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Builds a sample marketing video from a vehicle's photo gallery:
 * a Ken Burns slideshow of every photo, crossfading between shots,
 * with a branded overlay (title, specs, FOB price, stock id, domain)
 * and an optional music track.
 *
 * Memory-safe by design:
 *  1. Each photo is rendered to its own clip — one image input at a
 *     time, so memory stays flat regardless of photo count.
 *  2. Clips are crossfaded together in small batches, then the batch
 *     files are crossfaded — no single giant filtergraph, so it never
 *     OOMs on a modest server.
 * ffmpeg runs niced so it cannot starve the web server or database.
 *
 * Stand-alone proof-of-concept for client review — not the production
 * auto-generation pipeline.
 *
 * Examples:
 *   php artisan vehicle:sample-video
 *   php artisan vehicle:sample-video 509 --music=storage/app/music/track.mp3
 *   php artisan vehicle:sample-video 2023-nissan-serena-29758 --out=storage/app/demo.mp4
 */
class MakeVehicleSampleVideo extends Command
{
    protected $signature = 'vehicle:sample-video
        {vehicle? : Vehicle id or slug — defaults to the vehicle with the most photos}
        {--music= : Path to a music file to mix in (you provide this)}
        {--out= : Output .mp4 path — defaults to storage/app/sample-videos/}
        {--seconds=3.0 : Seconds each photo is shown}
        {--fade=0.5 : Crossfade duration between photos}';

    protected $description = 'Build a sample marketing video from a vehicle photo gallery';

    /** Photos crossfaded per batch — keeps every filtergraph shallow. */
    private const BATCH = 8;

    private int $fps = 30;

    public function handle(): int
    {
        if (trim((string) shell_exec('command -v ffmpeg')) === '') {
            $this->error('ffmpeg is not installed. Run: apt-get install -y ffmpeg');

            return self::FAILURE;
        }

        // ---- Resolve the vehicle -------------------------------------------------
        $arg = $this->argument('vehicle');
        if ($arg) {
            $vehicle = is_numeric($arg)
                ? Vehicle::find((int) $arg)
                : Vehicle::where('slug', $arg)->first();
        } else {
            $vehicle = Vehicle::withCount('media')->orderByDesc('media_count')->first();
        }

        if (! $vehicle) {
            $this->error('Vehicle not found.');

            return self::FAILURE;
        }

        // ---- Collect every photo (originals — best quality for video) ------------
        $images = $vehicle->getMedia('photos')
            ->map(fn ($m) => $m->getPath())
            ->filter(fn ($p) => is_file($p))
            ->values()
            ->all();

        $n = count($images);
        if ($n === 0) {
            $this->error("Vehicle #{$vehicle->id} has no readable photos.");

            return self::FAILURE;
        }

        $per = max(1.5, (float) $this->option('seconds'));
        $xf = max(0.2, min((float) $this->option('fade'), $per - 0.4));
        $total = round($n * $per - ($n - 1) * $xf, 3);

        $this->info("Vehicle #{$vehicle->id} — {$vehicle->title}");
        $this->info("Photos: {$n}   Length: ".round($total).'s');

        // ---- Music ---------------------------------------------------------------
        $music = $this->option('music');
        if ($music !== null) {
            $music = $this->resolvePath($music);
            if (! $music) {
                $this->error('Music file not found: '.$this->option('music'));

                return self::FAILURE;
            }
        }

        // ---- Output path ---------------------------------------------------------
        $out = $this->option('out')
            ? $this->resolvePath($this->option('out'), mustExist: false)
            : storage_path('app/sample-videos/vehicle-'.$vehicle->id.'.mp4');
        @mkdir(dirname($out), 0775, true);

        // ---- Branded text (written to files so drawtext escaping is safe) --------
        $tmp = sys_get_temp_dir().'/tjvid-'.Str::random(8);
        @mkdir($tmp, 0775, true);

        $specs = collect([
            $vehicle->year_first_reg,
            $vehicle->mileage_km ? number_format((int) $vehicle->mileage_km).' km' : null,
            $vehicle->transmission ? ucfirst((string) $vehicle->transmission) : null,
            $vehicle->fuel ? ucfirst((string) $vehicle->fuel) : null,
            $vehicle->engine_cc ? ((int) $vehicle->engine_cc).'cc' : null,
        ])->filter()->implode('   •   ');

        $price = $vehicle->price_on_request || ! $vehicle->price_fob
            ? 'Price on request'
            : 'FOB Japan   $'.number_format((float) $vehicle->price_fob);

        $stockId = $vehicle->stock_no ? '#'.$vehicle->stock_no : 'TOCO JAPAN';

        file_put_contents("{$tmp}/title.txt", (string) $vehicle->title);
        file_put_contents("{$tmp}/specs.txt", $specs);
        file_put_contents("{$tmp}/price.txt", $price);
        file_put_contents("{$tmp}/stock.txt", $stockId);
        file_put_contents("{$tmp}/site.txt", 'tocojapan.com');

        $font = $this->fontFile(bold: false);
        $fontBold = $this->fontFile(bold: true);

        // ---- Per-photo overlay filter chain (shared by every clip) ---------------
        $dt = fn (array $o) => 'drawtext='.collect($o)->map(fn ($v, $k) => "{$k}={$v}")->implode(':');
        $frames = (int) round($per * $this->fps);

        $chain = implode(',', [
            // Cover-crop to 1080p (modest 1.2x headroom for the zoom).
            'scale=2304:1296:force_original_aspect_ratio=increase,crop=2304:1296',
            // Gentle Ken Burns zoom.
            "zoompan=z='min(zoom+0.0010,1.12)':d={$frames}:".
                "x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=1920x1080:fps={$this->fps}",
            'setsar=1,format=yuv420p',
            // Branded lower-third.
            'drawbox=x=0:y=ih-210:w=iw:h=210:color=black@0.55:t=fill',
            $dt(['fontfile' => $fontBold, 'textfile' => "{$tmp}/title.txt",
                'fontcolor' => 'white', 'fontsize' => 54, 'x' => 70, 'y' => 'h-178']),
            $dt(['fontfile' => $font, 'textfile' => "{$tmp}/specs.txt",
                'fontcolor' => '0xD0D4DD', 'fontsize' => 30, 'x' => 70, 'y' => 'h-100']),
            $dt(['fontfile' => $fontBold, 'textfile' => "{$tmp}/price.txt",
                'fontcolor' => '0xFF4757', 'fontsize' => 42, 'x' => 'w-tw-70', 'y' => 'h-128']),
            // Corner badges: stock id (left), website (right).
            $dt(['fontfile' => $fontBold, 'textfile' => "{$tmp}/stock.txt",
                'fontcolor' => 'white', 'fontsize' => 30, 'x' => 70, 'y' => 56,
                'box' => 1, 'boxcolor' => '0xE30613@0.9', 'boxborderw' => 16]),
            $dt(['fontfile' => $fontBold, 'textfile' => "{$tmp}/site.txt",
                'fontcolor' => 'white', 'fontsize' => 30, 'x' => 'w-tw-70', 'y' => 56,
                'box' => 1, 'boxcolor' => '0xE30613@0.9', 'boxborderw' => 16]),
        ]);

        // ---- Render each photo to its own clip (one input → low memory) ---------
        $clips = [];
        foreach ($images as $i => $img) {
            $clip = sprintf('%s/clip-%03d.mp4', $tmp, $i);
            $clips[] = $clip;

            $this->line(sprintf('  [%2d/%d] %s', $i + 1, $n, basename($img)));

            $code = $this->ffmpeg([
                '-i', $img,
                '-filter_complex', "[0:v]{$chain}[v]",
                '-map', '[v]', '-t', (string) $per, '-r', (string) $this->fps,
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20',
                '-pix_fmt', 'yuv420p', '-an', $clip,
            ]);

            if ($code !== 0 || ! is_file($clip)) {
                $this->error('Failed to render clip for photo #'.($i + 1).'.');
                $this->cleanup($tmp);

                return self::FAILURE;
            }
        }

        // ---- Crossfade the clips together ---------------------------------------
        if ($n <= self::BATCH) {
            // Few enough photos — one shallow crossfade graph.
            $this->newLine();
            $this->info('Crossfading clips'.($music ? ' and mixing music…' : '…'));
            $code = $this->xfade($clips, array_fill(0, $n, $per), $xf, $music, $out, true);
        } else {
            // Crossfade in batches, then crossfade the batch files.
            $batchFiles = [];
            $batchDurations = [];
            foreach (array_chunk($clips, self::BATCH) as $bi => $group) {
                $bf = sprintf('%s/batch-%03d.mp4', $tmp, $bi);
                $k = count($group);
                $this->info('Crossfading batch '.($bi + 1).'…');

                $code = $this->xfade($group, array_fill(0, $k, $per), $xf, null, $bf, false);
                if ($code !== 0 || ! is_file($bf)) {
                    $this->error('Failed to crossfade batch '.($bi + 1).'.');
                    $this->cleanup($tmp);

                    return self::FAILURE;
                }
                $batchFiles[] = $bf;
                $batchDurations[] = $k * $per - ($k - 1) * $xf;
            }

            $this->newLine();
            $this->info('Joining batches'.($music ? ' and mixing music…' : '…'));
            $code = $this->xfade($batchFiles, $batchDurations, $xf, $music, $out, true);
        }

        $this->cleanup($tmp);

        if ($code !== 0 || ! is_file($out)) {
            $this->error('ffmpeg failed (exit '.$code.').');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done: '.$out.'  ('.$this->humanSize((int) filesize($out)).')');

        return self::SUCCESS;
    }

    /**
     * Crossfade a set of equal-codec video files into one output.
     *
     * @param  string[]  $files       Input clips, in order.
     * @param  float[]   $durations   Each clip's duration in seconds.
     * @param  bool      $fadeEnds    Fade the whole video in/out at the ends.
     */
    private function xfade(array $files, array $durations, float $xf, ?string $music, string $out, bool $fadeEnds): int
    {
        $count = count($files);
        $total = round(array_sum($durations) - ($count - 1) * $xf, 3);

        // Build the crossfade chain.
        $graph = [];
        if ($count === 1) {
            $graph[] = '[0:v]null[vx]';
        } else {
            $prev = '[0:v]';
            $cumulative = 0.0;
            for ($j = 1; $j < $count; $j++) {
                $cumulative += $durations[$j - 1];
                $offset = round($cumulative - $j * $xf, 3);
                $label = ($j === $count - 1) ? '[vx]' : "[t{$j}]";
                $graph[] = "{$prev}[{$j}:v]xfade=transition=fade:duration={$xf}:offset={$offset}{$label}";
                $prev = $label;
            }
        }

        if ($fadeEnds) {
            $graph[] = '[vx]fade=t=in:st=0:d=0.6,fade=t=out:st='.round($total - 0.8, 3).':d=0.8[v]';
            $vLabel = '[v]';
        } else {
            $vLabel = '[vx]';
        }

        if ($music) {
            $graph[] = "[{$count}:a]afade=t=in:d=2,afade=t=out:st=".round($total - 2.5, 3).':d=2.5[a]';
        }

        // Assemble the command.
        $args = [];
        foreach ($files as $f) {
            array_push($args, '-i', $f);
        }
        if ($music) {
            array_push($args, '-stream_loop', '-1', '-i', $music);
        }

        array_push($args,
            '-filter_complex', implode(';', $graph),
            '-map', $vLabel,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
        );
        if ($music) {
            array_push($args, '-map', '[a]', '-c:a', 'aac', '-b:a', '192k', '-shortest');
        }
        array_push($args, '-movflags', '+faststart', $out);

        return $this->ffmpeg($args);
    }

    /** Run ffmpeg, niced so it never starves the web server / database. */
    private function ffmpeg(array $args): int
    {
        $cmd = array_merge(
            ['nice', '-n', '15', 'ffmpeg', '-y', '-hide_banner', '-loglevel', 'error', '-nostdin'],
            $args,
        );

        $proc = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => STDERR], $pipes);

        return is_resource($proc) ? proc_close($proc) : 1;
    }

    private function cleanup(string $tmp): void
    {
        array_map('unlink', glob("{$tmp}/*") ?: []);
        @rmdir($tmp);
    }

    /** Resolve a path relative to the project root or CWD. */
    private function resolvePath(string $path, bool $mustExist = true): ?string
    {
        foreach ([$path, base_path($path), getcwd().'/'.$path] as $c) {
            if (is_file($c)) {
                return realpath($c);
            }
        }

        if ($mustExist) {
            return null;
        }

        return Str::startsWith($path, '/') ? $path : base_path($path);
    }

    /** Pick a usable TTF font for drawtext. */
    private function fontFile(bool $bold): string
    {
        $preferred = $bold
            ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
            : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

        if (is_file($preferred)) {
            return $preferred;
        }

        $any = trim((string) shell_exec("find /usr/share/fonts -name '*.ttf' 2>/dev/null | head -1"));

        return $any ?: $preferred;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes > 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
