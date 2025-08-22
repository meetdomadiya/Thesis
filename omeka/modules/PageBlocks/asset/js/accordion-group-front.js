$(document).ready(function () {
    $(".accordion-trigger").click(function () {
        const panel = $(this).parents(".accordion-panel");
        $(this)
            .attr("aria-expanded", $(this).attr("aria-expanded") === "true" ? "false" : "true");
        $(this)
            .find("span.fa")
            .toggleClass("fa-chevron-down")
            .toggleClass("fa-chevron-up")
        panel
            .find(".accordion-content")
            .toggleClass("collapsed");
    });
});