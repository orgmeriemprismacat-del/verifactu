var username, shortname, firstname, lastname, emailAlumne, noExisteixTutor=false;
var fraseNoExisteixTutor="<div class='error'><img class='capcalera' src='https://www.prisma.cat/campus/documents/activitat/banner_prisma_rectangle.png'></div>";
fraseNoExisteixTutor+="<div class='col-md-12 cos_error'>";
fraseNoExisteixTutor+="<p>El curs no disposa de cap tutor. Abans de l'inici del curs s'assignarà un tutor.</p>";
fraseNoExisteixTutor+="<p>Quan el curs tingui un tutor/a assignat, podràs contactar amb el tutor/a a través d'aquest formulari.</p>";
fraseNoExisteixTutor+="</div><div class='col-md-12 peu'>";
fraseNoExisteixTutor+="<p>C. Santa Eugènia, 102, esc. D, entl. 2a · 17006 Girona · 972 21 75 65 · 678 12 36 87 · ";
fraseNoExisteixTutor+="<a href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a> · secretaria@prisma.cat</p></div>";

let path = "https://campus.prisma.cat/intranet-collaboradors/alumnes/ajax/";
function mostrarTutories() {
   username = $('#username').html();
   shortname = $('#shortname').html();
   firstname = $('#firstname').html();
   lastname = $('#lastname').html();
   emailAlumne = $('#emailAlumne').html();

   var esquema = "<div id='cap' class='cap'></div>";
   esquema += "<div id='tutors' class='tutors'></div>";
   esquema += "<div id='contingut' class='contingut'></div>";

   esquema += "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>";
   esquema += "<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>";
   esquema += "<div class='modal-content'><div class='modal-header'>";
   esquema += "<p class='modal-title modal-title-danger' id='modalErrorsTitle'>Avís</p>";
   esquema += "<button type='button' class='close' data-dismiss='modal' aria-label='Close'>";
   esquema += "<span aria-hidden='true' class='white-text'>×</span></button></div>";
   esquema += "<div class='modal-body' id='modalErrorsBody'></div>";
   esquema += "<div class='modal-footer justify-content-center'>";
   esquema += "<a type='button' class='btn btn-danger waves-effect waves-light' aria-label='Close' data-dismiss='modal'>Tanca</a>";
   esquema += "</div></div></div></div>";

   $('#tot').html(esquema);
   mostrarCapcalera();
   mostrarTutors();
   mostrarFormulari();
}

function mostrarCapcalera() {
    var cns = $.ajax({
     url: path + "buscarTitol.php",
     method: "GET",
     data: {
       shortname: shortname
     },
     dataType: "html"
   });
   cns.done(function(titol) {
     $('#cap').html("<font class='titol'>"+titol+"</font>");
     if (noExisteixTutor) $('#tot').html(fraseNoExisteixTutor);
   });
}

function mostrarTutors() {
    var cns = $.ajax({
     url: path + "mostrarTutors.php",
     method: "GET",
     data: {
       username: username,
       shortname: shortname
     },
     dataType: "html"
   });
   cns.done(function(tutors) {
     $('#tutors').html(tutors);
     function clickTutor(e) {
        $(".foto-2").each(function(){
              $(this).removeClass('active');
          });
        $(this).addClass('active');
     }
     $(".foto-2").click(clickTutor);
     if ($('.tutors .cnt-tutors').html()=='')
        noExisteixTutor = true;
     if (noExisteixTutor) $('#tot').html(fraseNoExisteixTutor);
   });
}

function mostrarFormulari() {
    var cns = $.ajax({
     url: path + "mostrarFormulari.php",
     method: "GET",
     data: {
       username: username,
       firstname: firstname,
       lastname: lastname,
       emailAlumne: emailAlumne
     },
     dataType: "html"
   });
   cns.done(function(formulari) {
     $('#contingut').html(formulari);

     $('#enviar-dades').click(function () {
        var comprovacio = '';

        if ($('#email').val().trim().length == 0)  {
           comprovacio += '<li>El camp del correu electrònic està buit.</li>';
        }
        if ($('#consulta').val().trim().length == 0)  {
           comprovacio += '<li>El camp de la consulta està buit.</li>';
        }

        if ($('#tutor0').hasClass('foto-2')) { //Cas més d'un tutor
           var teActiu = false;
           $(".foto-2").each(function(){
              if ($(this).hasClass('active')) teActiu=true;
           });
           if (!teActiu)
              comprovacio += "<li>Cal triar el tutor/a qui li vols enviar un missatge.</li>";
        }

        if (comprovacio != '') {
           var missatgeError = "<ul class='errors'>";
           missatgeError += comprovacio + '</ul>';
           mostrarModalError(missatgeError);
        }
        else {
           enviarConsulta();
        }
     });

     if (noExisteixTutor) $('#tot').html(fraseNoExisteixTutor);
   });
}

function mostrarModalError(missatgeError) {
   $('#modalErrorsBody').html(missatgeError);
   $('#modalErrors').modal('show');
}

function enviarConsulta() {
   var missatge = $('#consulta').val();
	missatge = missatge.replace(/\n/g, "<br>");
	emailAlumne = $('#email').val();
   var nomTut, emailTut;
   if ($('#tutor0').hasClass('foto-2')) {//Cas més d'un tutor
      nomTut = $('.foto-2.active p').html();
      emailTut = $('.foto-2.active .emailTutor').html();
   }
   else {
      nomTut = $('.foto p').html();
      emailTut = $('.emailTutor').html();
   }
   var enviarConsulta = $.ajax({
		url: path + "enviarConsulta.php",
		method: "GET",
		data: {
			shortname: shortname,
			firstname: firstname,
			lastname: lastname,
			emailAlumne: emailAlumne,
			missatge: missatge,
			nomTut: nomTut,
			emailTut: emailTut
		},
		dataType: "html"
	});

  enviarConsulta.done(function(message) {
    if (message == "ok") {
       var email = $("#email").val();

       var missatgeConsultaOK="<div class='error'><img class='capcalera' ";
       missatgeConsultaOK+="src='https://www.prisma.cat/campus/documents/activitat/banner_prisma_rectangle.png'></div>";
       missatgeConsultaOK+="<div class='col-md-12 cos_error'>";
       missatgeConsultaOK+="<p>La teva consulta s'ha enviat correctament.</p>";
       missatgeConsultaOK+="<p>El teu tutor/a es posarà en contacte amb tu a trav&eacute;s del correu ";
       missatgeConsultaOK+="<strong><span class='correu_consulta'>" + email + "</span></strong>.</p>";
       missatgeConsultaOK+="<p>Gr&agrave;cies per contactar amb PrisMa.</p></div>";
       missatgeConsultaOK+="<div class='col-md-12 peu'>";
       missatgeConsultaOK+="<p>C. Santa Eugènia, 102, esc. D, entl. 2a · 17006 ";
       missatgeConsultaOK+="Girona · 972 21 75 65 · 678 12 36 87 · ";
       missatgeConsultaOK+="<a href='https://www.prisma.cat' target='_blank' ";
       missatgeConsultaOK+="alt='Associació PrisMa'>www.prisma.cat</a> ";
       missatgeConsultaOK+="· secretaria@prisma.cat</p></div>";

       $('#tot').html(missatgeConsultaOK);
    } else {
       mostrarModalError(message)
    }
  });
}
