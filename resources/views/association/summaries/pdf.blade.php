{{--
    ASSOCIATION MONTHLY REPORT, PDF

    Association\ReportController::downloadPdf() has rendered
    "association.summaries.pdf" since it was written, but the file did not
    exist, so every attempt to download an association's monthly report as
    a PDF threw "View [association.summaries.pdf] not found" and returned a
    500. Proposal section 76 requires the download, so this is the file that
    was missing rather than a change to the controller.

    The body is the MAO's monthly report template. That is deliberate, not
    laziness: both exports are built by the same
    App\Services\MonthlyReportBuilder::forPeriod(), so the $data they
    receive has exactly the same shape, and a second copy of 169 lines of
    table markup would only be a second place for the two to drift apart.
    The template takes an optional $association and changes its heading when
    one is given.
--}}
@include('mao.reports.pdf', ['data' => $data, 'association' => $association])
