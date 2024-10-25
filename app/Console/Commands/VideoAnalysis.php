<?php

namespace App\Console\Commands;

use App\Models\ServiceMedia;
use Illuminate\Console\Command;
use Sightengine\SightengineClient;

class VideoAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:video-analysis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $media = ServiceMedia::where([['is_approved', false], ['is_analyzed', false]])->get();
        if (count($media) <= 0) {
            echo 'No Links available for analysis';
            return;
        }

        foreach ($media as $item) {
            $client = new SightengineClient('1087841636', 'e5rJvaUPGnNP6HvUBDDpuQ6yUKYuPv6y');
            $output = $client->check(['nudity-2.1'])->video_sync($item->url); // Assuming you have a video_url field

            // Initialize nudity detected flag
            $nudityDetected = false;

            // Process the output
            if (isset($output->data->frames)) {
                foreach ($output->data->frames as $frame) {
                    $noNudityScore = $frame->nudity->none;
                    $nudityScore = 1 - $noNudityScore; // Calculate nudity score

                    if ($nudityScore > 0.5) { // Adjust threshold as needed
                        echo "Nudity detected in frame at position {$frame->info->position} with score: $nudityScore\n";
                        $nudityDetected = true; // Set flag if nudity is detected
                    } else {
                        echo "No significant nudity detected in frame at position {$frame->info->position}. Score: $nudityScore\n";
                    }
                }
            } else {
                echo "Error in API response: " . json_encode($output);
            }

            // Update the media item based on nudity detection
            $item->is_analyzed = true; // Mark as analyzed
            if (!$nudityDetected) {
                $item->is_approved = true;
            }
            $item->save(); // Save changes
        }
    }
}
