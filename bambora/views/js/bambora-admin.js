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
        var firstButton = control.children(".bambora-action-btn");
        var innerDiv = control.children("div:last");
        var cancelButton = innerDiv.children(".bambora-cancel-btn");
        var inputField = innerDiv.children(".bambora-action-input");
        var postButton = innerDiv.children(".bambora-action-submit");

        firstButton.click(function () {
            firstButton.removeClass("bambora-show");
            firstButton.addClass("bambora-hidden");
            innerDiv.removeClass("bambora-hidden");
            innerDiv.addClass("bambora-show");
            hideAllButtonsExceptMe(control);
            return false;
        });

        cancelButton.click(function () {
            innerDiv.removeClass("bambora-show");
            innerDiv.addClass("bambora-hidden");
            firstButton.removeClass("bambora-hidden");
            firstButton.addClass("bambora-show");
            showAllButtons();
            return false;
        });

        postButton.click(function () {
            var reg = new RegExp(/^(?:[\d]+([,.]?[\d]{0,3}))$/);
            if (inputField.length > 0 && inputField.name() !== "bambora-delete" && !reg.test(inputField.val())) {
                $("#bambora-format-error").toggle();
                return false;
            }

            hideAllButtons();
            $("#bambora-spinner").show();

            return true;
        });



        inputField.keydown(function (e) {
            var digit = String.fromCharCode(e.which || e.keyCode);
            if (e.which !== 8 && e.which !== 46 && !(e.which >= 37 && e.which <= 40) && e.which !== 110 && e.which !== 188
                && e.which !== 190 && e.which !== 35 && e.which !== 36 && !(e.which >= 96 && e.which <= 106)) {
                var reg = new RegExp(/^(?:\d+(?:,\d{0,3})*(?:\.\d{0,2})?|\d+(?:\.\d{0,3})*(?:,\d{0,2})?)$/);
                if (reg.test(digit)) {
                    console.log(e);
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

    $(".bambora-action-input")
        .focus(function () {
            if ($("#bambora-format-error").css("display") !== "none") {
                $("#bambora-format-error").toggle();
            }
        });
});
