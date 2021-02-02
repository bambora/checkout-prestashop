/**
 * Copyright (c) 2019. All rights reserved Bambora Online A/S.
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Bambora Online A/S
 * @copyright Bambora (https://bambora.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 */

$(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();

    $("#bambora-spinner").hide();

    if ($("#bambora-overlay").length > 0) {
        $("a#bambora-inline").fancybox({
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

        $("a#bambora-inline").trigger("click");
    }

    $.fn.bamboraTransactionControls = function () {
        this.children("div").each(function (item) {
            createTransactionControl($(this));
        });

        return this;
    }

    function createTransactionControl(control) {
        $("#bambora-spinner").hide();
        var firstDivChild = control.children("div:first");
        var firstButton = firstDivChild.children("div:first").children("input:first");
        var postButton = firstDivChild.children("div:last").children("div:last").children("input:first");
        var firstInnermostDiv = firstDivChild.children("div:first");
        var secondInnermostDiv = firstDivChild.children("div:last");
        var cancelButton = secondInnermostDiv.children("div:first").children("a:first");
        var inputField = secondInnermostDiv.children("div").eq(1).children("input:first");

        firstButton.click(function () {
            firstInnermostDiv.css("display", "none").removeClass("bambora-show");
            firstInnermostDiv.addClass("bambora-hidden");
            secondInnermostDiv.css("display", "inline-block").removeClass("bambora-hidden");
            secondInnermostDiv.addClass("bambora-show");
            hideAllButtonsExceptMe(control);
            return false;
        });

        postButton.click(function () {
            var reg = new RegExp(/^(?:[\d]+([,.]?[\d]{0,3}))$/);
            if (inputField.length > 0 && inputField.name()!== "bambora-delete" && !reg.test(inputField.val())) {
                $("#bambora-format-error").toggle();
                return false;
            }

            firstDivChild.hide();
            hideAllButtons();
            $("#bambora-spinner").show();

            return true;
        });

        cancelButton.click(function () {
            secondInnermostDiv.css("display", "none").removeClass("bambora-show");
            secondInnermostDiv.addClass("bambora-hidden");
            firstInnermostDiv.css("display", "inline-block").removeClass("bambora-hidden");
            firstInnermostDiv.addClass("bambora-show");
            showAllButtons();
            return false;
        });

        inputField.keydown(function (e) {
            var digit = String.fromCharCode(e.which || e.keyCode);
            if (e.which !== 8 && e.which !== 46 && !(e.which >= 37 && e.which <= 40) && e.which !== 110 && e.which !== 188
                && e.which !== 190 && e.which !== 35 && e.which !== 36 && !(e.which >= 96 && e.which <= 106)) {
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
        $("#bambora-transaction-controls-container").children("div").each(function (item) {
            //hiding all buttons in the container
            $("#bambora-transaction-controls-container").children("div").eq(item).hide();
        });
    }

    function hideAllButtonsExceptMe(me) {
        $("#bambora-transaction-controls-container").children("div").each(function (item) {
            $("#bambora-transaction-controls-container").children("div").eq(item).hide();
        });
        me.show();
    }

    function showAllButtons() {
        $("#bambora-transaction-controls-container").children("div").each(function (item) {
            //showing all buttons in the container
            $("#bambora-transaction-controls-container").children("div").eq(item).show();
        });
        $("#bambora-spinner").hide();
    }

    $("#bambora-transaction-controls-container").bamboraTransactionControls();

    $("#bambora-action-input")
        .focus(function () {
            if ($("#bambora-format-error").css("display") !== "none") {
                $("#bambora-format-error").toggle();
            }
        });
});
