/**
 * Bambora Online 2017
 *
 * @author    Bambora Online
 * @copyright Bambora (http://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

$(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();

    $("#bamboraSpinner").hide();

    if ($("#bambora_overlay").length > 0) {
        $("a#bambora_inline").fancybox({
            "scrolling": false,
            "transitionIn": "elastic",
            "transitionOut": "elastic",
            "speedIn": 400,
            "speedOut": 200,
            "overlayShow": true,
            "hideOnContentClick": false,
            "hideOnOverlayClick": false,
            "helpers": {
                "overlay": { "closeClick": true }
            }
        });

        $("a#bambora_inline").trigger("click");
    }

    $.fn.bamboraTransactionControls = function () {
        this.children("div").each(function (item) {
            createTransactionControl($(this));
        });

        return this;
    }

    function createTransactionControl(control) {
        $("#bamboraSpinner").hide();
        var firstDivChild = control.children("div:first");
        var firstButton = firstDivChild.children("div:first").children("input:first");
        var postButton = firstDivChild.children("div:last").children("div:last").children("input:first");
        var firstInnermostDiv = firstDivChild.children("div:first");
        var secondInnermostDiv = firstDivChild.children("div:last");
        var cancelButton = secondInnermostDiv.children("div:first").children("a:first");
        var inputField = secondInnermostDiv.children("div").eq(1).children("input:first");

        firstButton.click(function () {
            firstInnermostDiv.css("display", "none").removeClass("bambora_show");
            firstInnermostDiv.addClass("bambora_hidden");
            secondInnermostDiv.css("display", "inline-block").removeClass("bambora_hidden");
            secondInnermostDiv.addClass("bambora_show");
            hideAllButtonsExceptMe(control);
            return false;
        });

        postButton.click(function () {
            var reg = new RegExp(/^(?:[\d]+([,.]?[\d]{0,3}))$/);
            if (inputField.length > 0 && inputField.val() != "DELETE" && !reg.test(inputField.val())) {
                $("#bambora-format-error").toggle();
                return false;
            }

            firstDivChild.hide();
            hideAllButtons();
            $("#bamboraSpinner").show();

            return true;
        });

        cancelButton.click(function () {
            secondInnermostDiv.css("display", "none").removeClass("bambora_show");
            secondInnermostDiv.addClass("bambora_hidden");
            firstInnermostDiv.css("display", "inline-block").removeClass("bambora_hidden");
            firstInnermostDiv.addClass("bambora_show");
            showAllButtons();
            return false;
        });

        inputField.keydown(function (e) {
            var digit = String.fromCharCode(e.which || e.keyCode);
            if (e.which != 8 && e.which != 46 && !(e.which >= 37 && e.which <= 40) && e.which != 110 && e.which != 188
                && e.which != 190 && e.which != 35 && e.which != 36 && !(e.which >= 96 && e.which <= 106)) {
                var reg = new RegExp(/^(?:\d+(?:,\d{0,3})*(?:\.\d{0,2})?|\d+(?:\.\d{0,3})*(?:,\d{0,2})?)$/);
                if (reg.test(digit)) {
                    console.log(e);
                    return;
                } else {
                    return false;
                }
            }
        });
    }

    function hideAllButtons() {
        $("#divBamboraTransactionControlsContainer").children("div").each(function (item) {
            //hiding all buttons in the container
            $("#divBamboraTransactionControlsContainer").children("div").eq(item).hide();
        });
    }

    function hideAllButtonsExceptMe(me) {
        $("#divBamboraTransactionControlsContainer").children("div").each(function (item) {
            $("#divBamboraTransactionControlsContainer").children("div").eq(item).hide();
        });
        me.show();
    }

    function showAllButtons() {
        $("#divBamboraTransactionControlsContainer").children("div").each(function (item) {
            //showing all buttons in the container
            $("#divBamboraTransactionControlsContainer").children("div").eq(item).show();
        });
        $("#bamboraSpinner").hide();
    }

    $("#divBamboraTransactionControlsContainer").bamboraTransactionControls();

    $("#bambora-action-input")
        .focus(function () {
            if ($("#bambora-format-error").css('display') !== 'none') {
                $("#bambora-format-error").toggle();
            }
        });
});