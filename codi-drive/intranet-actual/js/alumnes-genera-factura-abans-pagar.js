let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let idsInsc = [];
let uc004EntityId = 0;
let uc004CsrfToken = '';
let uc004Fingerprint = '';
let uc004PreviewObservations = '';
let uc004Preview = null;

/* El loading global es conserva per coherència amb la resta de la intranet. */
$(document).bind("ajaxSend", function(){
    mostrarModalLoading();
}).bind("ajaxComplete", function(){
    amagarLoadingModal();
});

var requestMain = $.ajax({
    url: "https://intranet.prisma.cat/ajax/mostrarMain.php?url=" + urlPagina,
    method: "GET",
    data: { url: urlPagina },
    dataType: "html"
});

requestMain.done(function(message) {
    $('.mainpanel').html(message);

    initializeSecureUc004();

    $(window).click(function() {
        $('.select .select-list').hide();
        $('.select').find("i").removeClass("fa-angle-up").addClass("fa-angle-down");
    });

    $('#content-page').on('focus', '.form-control', function() {
        $(this).parent().removeClass('element-cercat-marcat');
    });

    $('#content-page').on('blur', '.form-control', function() {
        if ($(this).val().trim() === '') {
            $(this).removeClass('element-cercat-marcat');
        } else {
            $(this).addClass('element-cercat-marcat');
        }
    });

    $("#content-page .select").click(function(e) {
        e.stopPropagation();
        var list = $(this).find("ul");
        var triangle = $(this).find("i");
        e.preventDefault();
        list.toggle();

        if (list.is(":hidden")) {
            triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
        } else {
            triangle.removeClass("fa-angle-down").addClass("fa-angle-up");
        }
    });

    $("#content-page .select").on("click", "li", function(e) {
        var text = $(this).text();
        var element = $(this).parent().prev();
        var list = $(this).closest("ul");
        var triangle = $(this).parent().next();

        e.preventDefault();
        e.stopPropagation();

        element.text(text);
        list.hide();
        triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
        $(this).parent().parent().prev().addClass('active');

        if ($(this).parent().parent().attr('id') === 'entitat-dispo') {
            uc004EntityId = parseInt($(this).attr('data-entity-id'), 10) || 0;
            invalidateUc004Preview();

            if (uc004EntityId > 0 && selectedInscriptionIds().length > 0) {
                refreshUc004Preview(false);
            }
        }
    });

    $("#genera-factura-pas-1").keyup(function(evObject) {
        if (evObject.keyCode === 13) {
            $('#cercar-alumne').click();
        }
    });

    $('#cercar-alumne').on('click', function() {
        var dni = $('#dni').val().trim();

        if (dni === '') {
            showUc004Error("Omple el NIF/NIE per poder fer la cerca.");
            return;
        }

        $.ajax({
            url: path + "alumnes/mostrarInformacioInscripcio_generaFactura.php",
            method: "GET",
            data: { dni: dni },
            dataType: "html"
        }).done(function(res) {
            $('#resultats-cerca').off();
            $('#insc-rel-fact-rel').off();

            if (String(res).toLowerCase().includes("error")) {
                showUc004Error("Hi ha hagut un error a l'hora de mostrar la informació de la inscripció.");
                return;
            }

            $('#resultats-cerca').html(res).show();

            $('#resultats-cerca').on('click', '.add-inscripcio', function() {
                addSelectedInscription($(this));
            });

            $('#insc-rel-fact-rel').on('click', '.remove-inscripcio', function() {
                removeSelectedInscription($(this));
            });
        }).fail(function(jqXHR, textStatus, errorThrown) {
            errorFunction(
                jqXHR,
                textStatus,
                errorThrown,
                "Hi ha hagut algun error a l'hora de fer la consulta: "
            );
        });
    });

    $('#genera-factura-pas-1').on('click', '#continue-pas-2', function() {
        if (!tePermisEdicio) {
            mostrarModalNoTensPermisos();
            return;
        }

        idsInsc = selectedInscriptionIds();

        if (idsInsc.length === 0) {
            showUc004Error("No s'ha seleccionat cap inscripció.");
            return;
        }

        invalidateUc004Preview();
        clearUc004PreviewFields();

        $('#genera-factura-pas-1').fadeOut('fast', function() {
            $('#genera-factura-pas-2').fadeIn('fast');
        });
    });

    $('#observacions').on('change blur', function() {
        if (uc004EntityId > 0 && selectedInscriptionIds().length > 0) {
            refreshUc004Preview(true);
        } else {
            invalidateUc004Preview();
        }
    });

    $('#genera-factura-pas-2').on('click', '#continue-pas-3', function() {
        if (!tePermisEdicio) {
            mostrarModalNoTensPermisos();
            return;
        }

        idsInsc = selectedInscriptionIds();

        if (idsInsc.length === 0) {
            showUc004Error("No hi ha inscripcions seleccionades.");
            return;
        }

        if (uc004EntityId <= 0) {
            showUc004Error("Selecciona una entitat amb identificador intern vàlid.");
            return;
        }

        var observations = $('#observacions').val().trim();

        if (
            uc004Fingerprint === ''
            || uc004Preview === null
            || observations !== uc004PreviewObservations
        ) {
            refreshUc004Preview(true);
            return;
        }

        secureUc004Request({
            action: 'confirm',
            inscription_ids: idsInsc,
            entity_id: uc004EntityId,
            expected_fingerprint: uc004Fingerprint,
            observations: observations
        }).done(function(response) {
            if (!response || response.ok !== true) {
                showUc004Error(
                    response && response.error
                        ? response.error
                        : "No s'ha pogut emetre la factura SIF."
                );
                return;
            }

            renderUc004IssuedInvoice(response);
            $('#genera-factura-pas-2').fadeOut('fast', function() {
                $('#genera-factura-pas-3').fadeIn('fast');
            });

            afegirHeaderModalSuccess("Factura SIF creada!");
            afegirTextModalSuccess(
                "S'ha emès la factura " + String(response.num_visible || '') +
                " i queda pendent de cobrament."
            );
            mostrarModalSuccess();
        }).fail(function(jqXHR) {
            if (jqXHR.status === 409) {
                invalidateUc004Preview();
                showUc004Error(
                    "Les dades han canviat o una altra operació ja cobreix alguna inscripció. " +
                    "Genera un nou preview abans de confirmar."
                );
                refreshUc004Preview(false);
                return;
            }

            if (jqXHR.status === 401 || jqXHR.status === 403) {
                showUc004Error("No tens autorització per emetre aquesta factura.");
                return;
            }

            var message = jqXHR.responseJSON && jqXHR.responseJSON.error
                ? jqXHR.responseJSON.error
                : "No s'ha pogut emetre la factura SIF.";
            showUc004Error(message);
        });
    });
});

requestMain.fail(function(jqXHR, textStatus, errorThrown) {
    errorFunction(
        jqXHR,
        textStatus,
        errorThrown,
        "Hi ha hagut un error en el request Main: "
    );
});

function initializeSecureUc004() {
    setUc004AuthoritativeFields();

    var tokenRequest = $.ajax({
        url: path + "alumnes/sifFacturaAbansPagarToken.php",
        method: "GET",
        dataType: "json",
        cache: false
    });

    var entitiesRequest = $.ajax({
        url: path + "alumnes/sifFacturaAbansPagarEntitats.php",
        method: "GET",
        dataType: "json",
        cache: false
    });

    $.when(tokenRequest, entitiesRequest).done(function(tokenResult, entitiesResult) {
        var tokenResponse = tokenResult[0];
        var entitiesResponse = entitiesResult[0];

        if (!tokenResponse || tokenResponse.ok !== true || !tokenResponse.csrf_token) {
            disableUc004Mutation("No s'ha pogut iniciar la protecció CSRF.");
            return;
        }

        uc004CsrfToken = String(tokenResponse.csrf_token);

        if (!entitiesResponse || entitiesResponse.ok !== true) {
            disableUc004Mutation("No s'han pogut carregar les entitats autoritzades.");
            return;
        }

        populateUc004Entities(entitiesResponse.entities || []);
    }).fail(function(jqXHR) {
        if (jqXHR.status === 401 || jqXHR.status === 403) {
            disableUc004Mutation("No tens permisos d'edició per aquesta operació.");
            return;
        }

        disableUc004Mutation("No s'ha pogut inicialitzar el circuit segur de factura.");
    });
}

function setUc004AuthoritativeFields() {
    $('#concepte1').prop('readonly', true).attr('aria-readonly', 'true');
    $('#preu').prop('readonly', true).attr('aria-readonly', 'true');
}

function populateUc004Entities(entities) {
    var list = $('#entitat-dispo .select-list');
    list.empty();

    entities.forEach(function(entity) {
        var id = parseInt(entity.id, 10);
        if (!Number.isInteger(id) || id <= 0) {
            return;
        }

        $('<li>')
            .addClass('border-bottom')
            .attr('data-entity-id', id)
            .append(
                $('<a>')
                    .attr('href', '#')
                    .text(entity.label || entity.name || ('Entitat ' + id))
            )
            .appendTo(list);
    });

    $('#entitat-dispo .element-selected').text('');
    uc004EntityId = 0;
    invalidateUc004Preview();
}

function addSelectedInscription(button) {
    var id = button.attr('id');
    var parts = String(id).split('-');
    var idInsc = parts.length > 2 ? parts[2] : '';

    if (!/^\d+$/.test(idInsc)) {
        showUc004Error("No s'ha pogut identificar la inscripció.");
        return;
    }

    var element = button.parent();
    var cells = "";

    while (element.prev().length > 0) {
        element = element.prev();
        var td = "<td>";
        if (element.attr('id')) {
            td = "<td ";
            if (
                element.attr('id').split('-')[0] === 'titol'
                || element.attr('id').split('-')[0] === 'hores'
            ) {
                td += "class='hide' ";
            }
            td += "id='" + element.attr('id') + "'>";
        }
        cells = td + element.html() + "</td>" + cells;
    }

    var row = "<tr>" + cells
        + "<td><i id='remove-insc-" + idInsc
        + "' class='material-icons remove-inscripcio'>remove_circle</i></td></tr>";

    if ($('#insc-rel-fact-rel').html().includes("table")) {
        $('#insc-rel-fact-rel table tbody').append(row);
    } else {
        var thead = button.parent().parent().parent().prev().html();
        $('#insc-rel-fact-rel').html(
            "<table class='table table-hover table-striped table-hover text-center'>"
            + "<thead>" + thead + "</thead><tbody>" + row + "</tbody></table>"
        );
    }

    button.addClass('no-disponible').removeClass('add-inscripcio');
    invalidateUc004Preview();

    afegirHeaderModalSuccess("Afegit!");
    afegirTextModalSuccess("La inscripció s'ha afegit correctament.");
    mostrarModalSuccess();
    setTimeout(function() {
        amagarModalSuccess();
    }, 1000);
}

function removeSelectedInscription(button) {
    var parts = String(button.attr('id')).split('-');
    var idInsc = parts.length > 2 ? parts[2] : '';

    button.parent().parent().remove();

    if ($('#insc-rel-fact-rel table tbody tr').length === 0) {
        $('#insc-rel-fact-rel').html(
            "<p>No hi ha inscripcions relacionades amb la factura a generar.</p>"
        );
    }

    if (/^\d+$/.test(idInsc)) {
        $('#add-insc-' + idInsc)
            .addClass('add-inscripcio')
            .removeClass('no-disponible');
    }

    idsInsc = selectedInscriptionIds();
    invalidateUc004Preview();
}

function selectedInscriptionIds() {
    var ids = [];
    var seen = {};

    $('#insc-rel-fact-rel .remove-inscripcio').each(function() {
        var parts = String($(this).attr('id') || '').split('-');
        var value = parts.length > 2 ? parts[2] : '';

        if (/^\d+$/.test(value)) {
            var id = parseInt(value, 10);
            if (id > 0 && !seen[id]) {
                seen[id] = true;
                ids.push(id);
            }
        }
    });

    ids.sort(function(a, b) { return a - b; });
    return ids;
}

function refreshUc004Preview(requireReviewAgain) {
    idsInsc = selectedInscriptionIds();

    if (idsInsc.length === 0 || uc004EntityId <= 0) {
        invalidateUc004Preview();
        return;
    }

    var observations = $('#observacions').val().trim();

    secureUc004Request({
        action: 'preview',
        inscription_ids: idsInsc,
        entity_id: uc004EntityId,
        observations: observations
    }).done(function(response) {
        if (!response || response.ok !== true) {
            invalidateUc004Preview();
            showUc004Error(
                response && response.error
                    ? response.error
                    : "No s'ha pogut preparar el preview SIF."
            );
            return;
        }

        uc004Preview = response;
        uc004Fingerprint = String(response.fingerprint || '');
        uc004PreviewObservations = observations;

        if (!/^[a-f0-9]{64}$/.test(uc004Fingerprint)) {
            invalidateUc004Preview();
            showUc004Error("El SIF no ha retornat un fingerprint de preview vàlid.");
            return;
        }

        renderUc004Preview(response);

        if (requireReviewAgain) {
            afegirHeaderModalSuccess("Preview actualitzat");
            afegirTextModalSuccess(
                "Les dades s'han reconstruït des del servidor. Revisa-les i torna a prémer «Genera factura»."
            );
            mostrarModalSuccess();
        }
    }).fail(function(jqXHR) {
        invalidateUc004Preview();

        if (jqXHR.status === 401 || jqXHR.status === 403) {
            showUc004Error("No tens autorització per preparar aquesta factura.");
            return;
        }

        var message = jqXHR.responseJSON && jqXHR.responseJSON.error
            ? jqXHR.responseJSON.error
            : "No s'ha pogut preparar el preview SIF.";
        showUc004Error(message);
    });
}

function renderUc004Preview(response) {
    var context = response.context || {};
    var totals = response.totals || {};
    var billing = response.billing || {};
    var lines = response.lines || [];

    setUc004FieldValue('#concepte1', context.legacy_concept1 || '');
    setUc004FieldValue('#preu', totals.total ? String(totals.total) + ' €' : '');

    var wrapper = $('#uc004-sif-preview-info');
    if (wrapper.length === 0) {
        wrapper = $('<div>')
            .attr('id', 'uc004-sif-preview-info')
            .addClass('alert alert-info mt-3');
        $('#genera-factura-pas-2 .card-body').append(wrapper);
    }

    wrapper.empty();
    wrapper.append($('<strong>').text('Preview SIF autoritatiu'));
    wrapper.append($('<div>').text(
        'Receptor: ' + String(billing.name || '') + ' · ' + String(billing.nif || '')
    ));
    wrapper.append($('<div>').text(
        'Inscripcions: ' + String((response.selection || {}).count || idsInsc.length)
        + ' · Línies: ' + String(lines.length)
        + ' · Total: ' + String(totals.total || '')
    ));
    wrapper.append($('<div>').text(
        'Fingerprint: ' + uc004Fingerprint.substring(0, 16) + '…'
    ));
}

function renderUc004IssuedInvoice(response) {
    var body = $('#genera-factura-pas-3 .card-body');
    var footer = $('#genera-factura-pas-3 .card-footer');

    body.empty();
    footer.empty().hide();

    var wrapper = $('<div>').addClass('uc004-sif-issued');
    wrapper.append(
        $('<div>')
            .addClass('alert alert-success')
            .text(
                'Factura SIF emesa. El cobrament continua pendent i es registrarà sobre el mateix UUID.'
            )
    );

    var list = $('<dl>').addClass('row');
    appendUc004Definition(list, 'Número', response.num_visible || '');
    appendUc004Definition(list, 'UUID', response.uuid_factura || '');
    appendUc004Definition(list, 'Reutilitzada', response.idempotency_reused ? 'Sí' : 'No');
    appendUc004Definition(list, 'Cobrament', 'PENDING');
    appendUc004Definition(
        list,
        'Receptor',
        response.billing
            ? String(response.billing.name || '') + ' · ' + String(response.billing.nif || '')
            : ''
    );
    appendUc004Definition(
        list,
        'Inscripcions',
        response.selection && response.selection.ids
            ? response.selection.ids.join(', ')
            : idsInsc.join(', ')
    );

    wrapper.append(list);
    wrapper.append(
        $('<p>')
            .addClass('text-muted')
            .text(
                'El document fiscal SIF s\'ha de servir pel circuit de documents per UUID; '
                + 'aquesta pantalla ja no genera ni elimina PDFs temporals llegats.'
            )
    );

    body.append(wrapper);
}

function appendUc004Definition(list, label, value) {
    if (value === undefined || value === null || String(value) === '') {
        return;
    }

    list.append($('<dt>').addClass('col-sm-4').text(label));
    list.append($('<dd>').addClass('col-sm-8').text(String(value)));
}

function secureUc004Request(payload) {
    if (!/^[a-f0-9]{64}$/.test(uc004CsrfToken)) {
        return $.Deferred()
            .reject({
                status: 403,
                responseJSON: { error: 'CSRF no inicialitzat' }
            })
            .promise();
    }

    return $.ajax({
        url: path + "alumnes/sifFacturaAbansPagar.php",
        method: "POST",
        contentType: "application/json; charset=utf-8",
        dataType: "json",
        headers: {
            'X-CSRF-Token': uc004CsrfToken
        },
        data: JSON.stringify(payload)
    });
}

function invalidateUc004Preview() {
    uc004Fingerprint = '';
    uc004PreviewObservations = '';
    uc004Preview = null;
    $('#uc004-sif-preview-info').remove();
}

function clearUc004PreviewFields() {
    setUc004FieldValue('#concepte1', '');
    setUc004FieldValue('#preu', '');
}

function setUc004FieldValue(selector, value) {
    var field = $(selector);
    if (field.is('input, textarea')) {
        field.val(value);
    } else {
        field.text(value);
    }

    if (String(value).trim() !== '') {
        field.prev().addClass('active');
    }
}

function disableUc004Mutation(message) {
    $('#continue-pas-2, #continue-pas-3').prop('disabled', true).addClass('no-disponible');
    showUc004Error(message);
}

function showUc004Error(message) {
    afegirHeaderModalError("Alerta");
    afegirTextModalError(String(message));
    mostrarModalError();
}
