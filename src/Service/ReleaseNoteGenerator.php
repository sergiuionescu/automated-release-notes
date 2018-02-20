<?php

namespace FoodKit\ReleaseNote\Service;

use Foodkit\ReleaseNote\IssueTracker\IssueTrackerInterface;
use Foodkit\ReleaseNote\Parser\ParserInterface;

class ReleaseNoteGenerator
{
    const FORMAT_GITHUB = 'github';
    const FORMAT_SLACK = 'slack';
    const FORMAT_JSON = 'json';
    const FORMAT_HTML = 'html';

    /** @var IssueTrackerInterface */
    protected $issueTracker;

    private $parser;

    /** @var string */
    private $format;

    public function __construct(
        IssueTrackerInterface $issueTracker,
        ParserInterface $parser,
        $format = self::FORMAT_GITHUB
    ) {
        $this->issueTracker = $issueTracker;
        $this->parser = $parser;
        $this->format = $format;
    }

    public function generate($start, $end)
    {
        $commits = $this->parser->getCommits($start, $end);

        $tickets = [];
        foreach ($commits as $commit) {
            if (preg_match($this->issueTracker->getIssueRegex(), $commit, $matches)) {
                $ticket = $matches[0];
                if (!in_array($ticket, $tickets)) {
                    $tickets[] = $ticket;
                }
            }
        }

        $summaries = [];
        foreach ($tickets as $ticket) {
            if ($summary = $this->issueTracker->getIssueSummary($ticket)) {
                $summaries[$ticket] = $summary;
            }
        }

        $items = [];

        foreach ($summaries as $key => $summary) {
            $items[] = [
                'key' => $key,
                'url' => $this->issueTracker->getIssueURL($key),
                'summary' => $summary
            ];
        }

        $compareUrl = $this->parser->getCompareUrl($start, $end);

        return $this->format($this->format, $end, $items, $compareUrl);
    }

    private function format($format, $release, $items, $compareUrl)
    {
        switch ($format) {

            case self::FORMAT_GITHUB:

                $notes = "# Release notes - $release\n\n";

                foreach ($items as $item) {
                    $notes .= "* [{$item['key']}]({$item['url']}) - {$item['summary']}\n";
                }

                if (!empty($compareUrl)) {
                    $notes .= "\n[See release commits]($compareUrl)";
                }

                return $notes;

            case self::FORMAT_SLACK:

                $notes = "";

                foreach ($items as $item) {
                    $notes .= "<{$item['url']}|{$item['key']}> - {$item['summary']}\n";
                }

                if (!empty($compareUrl)) {
                    $notes .= "\n<$compareUrl|See release commits>";
                }

                return $notes;

            case self::FORMAT_JSON:

                $result = [
                    'data' => [
                        'tag' => $release,
                        'issues' => $items,
                        'markdown' => $this->format(self::FORMAT_GITHUB, $release, $items, $compareUrl),
                    ]
                ];

                return json_encode($result, JSON_PRETTY_PRINT);

            case self::FORMAT_HTML:

                $notes = "<b>Release notes: $release</b></br>";
                $notes .= '<ul>';
                foreach ($items as $item) {
                    $notes .= "<li>[<a href='{$item['url']}'>{$item['key']}</a>] - {$item['summary']}</li>";
                }
                $notes .= '</ul>';
                if (!empty($compareUrl)) {
                    $notes .= "<a href='{$compareUrl}'>See release commits</a>";
                }

                return $notes;
        }
    }
}
