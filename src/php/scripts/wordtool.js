/**
 *  This is main file with jquery based handy methods to provide server side's functionality for client.
 *
 *
 *  Life cycle:
 *      - page load, register location hash change handler
 *      - if location hash changed, do appropriate request
 */


var ACTION_CODE_GET_RECENT = 1;
var ACTION_CODE_UPLOAD_NEW = 2;
var ACTION_CODE_GET_TEXT_INFO = 4;


var controllerUrl = "./ajax/";
var uploadFields;
var uploadTips;
var uploadName;
var uploadText;

$(function() {
    var uploadName = $( "#name" );
    var uploadText = $( "#text" );
    uploadFields = $( [] ).add( uploadName ).add( uploadText );
    uploadTips = $( ".validateTips" );

    function checkLength( o, n, min, max ) {
        if ( o.val().length > max || o.val().length < min ) {
            o.addClass( "ui-state-error" );
            updateTips( "Field length '" + n + "' must be bigger than " +
                min + " and less than " + max + " symbols." );
            return false;
        } else {
            return true;
        }
    }

    function updateTips( t ) {
        uploadTips
            .text( t )
            .addClass( "ui-state-highlight" );
        setTimeout(function() {
            uploadTips.removeClass( "ui-state-highlight", 1500 );
        }, 500 );
    }

    $( "#new-text-upload" ).dialog({
        autoOpen: false,
        height: 600,
        width: 800,
        modal: true,
        buttons: {
            "Upload text for analysis": function() {
                var bValid = true;
                uploadFields.removeClass( "ui-state-error" );

                bValid = bValid && checkLength( uploadName, "Name", 3, 50 );
                bValid = bValid && checkLength( uploadText, "Text", 10, 100000 );

                if ( bValid ) {
                    jQuery.post(controllerUrl,
                        {
                            act : ACTION_CODE_UPLOAD_NEW,
                            name : uploadName.val(),
                            text : uploadText.val()
                        }, genericHandler
                    );
                }
            },
            "Cancel": function() {
                $( this ).dialog( "close" );
            }
        },
        close: function() {
            uploadFields.val( "" ).removeClass( "ui-state-error" );
        }
    });

$( "#upload-text-button" )
    .click(function() {
        $( "#new-text-upload" ).dialog( "open" );
    });

getRecent();
});

function getRecent() {
    jQuery.post(controllerUrl,
        {
            act : ACTION_CODE_GET_RECENT
        }, genericHandler
    );
}
function showText(id) {
    jQuery.post(controllerUrl,
        {
            act : ACTION_CODE_GET_TEXT_INFO,
            id : id
        }, genericHandler
    );
}

function genericHandler(result)
{
    var data = eval("("+result+")");
    if(data.action == 'error')
    {
        alert('Error occured. ' + (data.message ? data.message : ''));
    }
    if(data.type == 'recent')
    {
        //loaded recent

        var list = "&nbsp;<br />";
        if(data.records.length == 0)
        {
            list += " No texts. ";
        }
        for (var x = 0; x < data.records.length; x++)
        {
            var r = data.records[x];
            list += "<a href='javascript:void(0);' onclick='showText(" + r.id +")' > " + r.content + " (" + r.created + ") </a>  <br />";
        }

        $( "#leftpart").html(list);
    }
    if(data.type == 'uploaddone')
    {
        showText(data.id);
        getRecent();
    }
    if(data.type == 'text')
    {
        $( "#new-text-upload" ).dialog( "close" );
        $( "#mainpart").html(data.text);

        var freqinfo = "";
        for (var x = 0; x < data.freq_info.length; x++)
        {
            var wf = data.freq_info[x];
            freqinfo += wf.a + " <strong>+</strong> " + wf.b + " <strong>=</strong> " + wf.f +  " <br />";
        }
        $( "#freqpart").html(freqinfo);

        var similarpart = "";
        for (var x = 0; x < data.similar.length; x++)
        {
            var sim = data.similar[x];
            similarpart += "<a href='javascript:void(0);' onclick='showText(" + sim.id +")' > " + sim.content + " (" + sim.created + "); Matched " + sim.cnt+ " word combinations, total weight sum " + sim.freq+" </a>  <br />";
        }
        $( "#similarpart").html(similarpart);
    }
}

