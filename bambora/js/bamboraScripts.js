$(document).ready(function () {
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
            "helpers" : { 
            "overlay" : {"closeClick": true}
            }

        });

        $("a#bambora_inline").trigger("click");

    } 

    $.fn.bamboraTransactionControls = function () {
        this.children("div").each(function(item) {
            createTransactionControl($(this));
        });

        return this;
    }

    function createTransactionControl(control) {

        $("#bamboraSpinner").hide();
        var firstdivChild = control.children("div:first");
        var firstbutton = firstdivChild.children("div:first").children("input:first");
        var postButton = firstdivChild.children("div:last").children("div:last").children("input:first");
        var firstInnermostDiv = firstdivChild.children("div:first");
        var secondInnermostDiv = firstdivChild.children("div:last");
        var cancelbutton = secondInnermostDiv.children("div:first").children("a:first");
        var inputfield = secondInnermostDiv.children("div").eq(1).children("input:first");

        firstbutton.click(function () {
            firstInnermostDiv.css("display", "none").removeClass("bambora_show");
            firstInnermostDiv.addClass("bambora_hidden");
            secondInnermostDiv.css("display", "inline-block").removeClass("bambora_hidden");
            secondInnermostDiv.addClass("bambora_show");
            hideAllButtonsExceptMe(control);
            return false;
        });

        postButton.click(function () {
            var reg = new RegExp(/^(?:\d+(?:,\d{0,3})*(?:\.\d{0,2})?|\d+(?:\.\d{0,3})*(?:,\d{0,2})?)$/);

            if (inputfield.length > 0 && inputfield.val() != "DELETE" && !reg.test(inputfield.val())) {
                $("#divBamboraTransactionControlsContainer").append('<p style="color:red">*</p>');
                return false;
            }

            firstdivChild.hide();
            hideAllButtons();
            $("#bamboraSpinner").show();

            return true;
        });

        cancelbutton.click(function () {
            secondInnermostDiv.css("display", "none").removeClass("bambora_show");
            secondInnermostDiv.addClass("bambora_hidden");
            firstInnermostDiv.css("display", "inline-block").removeClass("bambora_hidden");
            firstInnermostDiv.addClass("bambora_show");
            showAllButtons();       
            return false;
        });

        inputfield.keydown(function (e) {
            var digit = String.fromCharCode(e.which || e.keyCode);
            if (e.which != 8 && e.which != 46 && !(e.which >= 37 && e.which <= 40) && e.which != 110 && e.which != 188
                && e.which != 190 && e.which != 35 && e.which != 36 && !(e.which >= 96 && e.which <= 106)) {
                var reg = new RegExp(/^(?:\d+(?:,\d{0,3})*(?:\.\d{0,2})?|\d+(?:\.\d{0,3})*(?:,\d{0,2})?)$/);
                if (reg.test(digit)) {
                    return;
                } else {
                    return false;
                }
            }
        });
    }

    function hideAllButtons() {
        $("#divBamboraTransactionControlsContainer").children("div").each(function(item) {
            //hiding all buttons in the container
            $("#divBamboraTransactionControlsContainer").children("div").eq(item).hide();
        });
    }

    function hideAllButtonsExceptMe(me) {
        $("#divBamboraTransactionControlsContainer").children("div").each(function(item) {
            $("#divBamboraTransactionControlsContainer").children("div").eq(item).hide();
        });
        me.show();
    }

    function showAllButtons() {
        $("#divBamboraTransactionControlsContainer").children("div").each(function(item) {
            //showing all buttons in the container
            $("#divBamboraTransactionControlsContainer").children("div").eq(item).show();
        });
        $("#bamboraSpinner").hide();
    }

    $("#divBamboraTransactionControlsContainer").bamboraTransactionControls();

 

});
















