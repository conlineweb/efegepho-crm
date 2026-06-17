/*=============================================
CARGAR TABLA DINAMICA DE CURSOS
=============================================*/

// $.ajax({

//     url: "ajax/datatable-cursos.ajax.php",
//     success:function(respuesta){
        
//         console.log("respuesta", respuesta);

//     }

// });

$('.tablaCursos').DataTable( {
    "ajax": "ajax/datatable-cursos.ajax.php",
    "deferRender": true,
    "retrieve": true,
    "processing": true,
     "language": {

            "sProcessing":     "Procesando...",
            "sLengthMenu":     "Mostrar _MENU_ registros",
            "sZeroRecords":    "No se encontraron resultados",
            "sEmptyTable":     "Ningún dato disponible en esta tabla",
            "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_",
            "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0",
            "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
            "sInfoPostFix":    "",
            "sSearch":         "Buscar:",
            "sUrl":            "",
            "sInfoThousands":  ",",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
            "sFirst":    "Primero",
            "sLast":     "Último",
            "sNext":     "Siguiente",
            "sPrevious": "Anterior"
            },
            "oAria": {
                "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
                "sSortDescending": ": Activar para ordenar la columna de manera descendente"
            }

    }

});

/*=============================================
SUBIR IMAGEN DE LA PORTADA DEL PRODUCTO
=============================================*/

$(".nuevaFoto").change(function(){

    var imagen = this.files[0];
    
    /*=============================================
    VALIDAMOS EL FORMATO DE LA IMAGEN SEA JPG O PNG
    =============================================*/

    if(imagen["type"] != "image/jpeg" && imagen["type"] != "image/png"){

        $(".nuevaFoto").val("");

         swal({
              title: "Error al subir la imagen",
              text: "¡La imagen debe estar en formato JPG o PNG!",
              type: "error",
              confirmButtonText: "¡Cerrar!"
            });

    }else if(imagen["size"] > 2000000){

        $(".nuevaFoto").val("");

         swal({
              title: "Error al subir la imagen",
              text: "¡La imagen no debe pesar más de 2MB!",
              type: "error",
              confirmButtonText: "¡Cerrar!"
            });

    }else{

        var datosImagen = new FileReader;
        datosImagen.readAsDataURL(imagen);

        $(datosImagen).on("load", function(event){

            var rutaImagen = event.target.result;

            $(".previsualizar").attr("src", rutaImagen);

        })
    }
})

var idCursoEli = 0;
var nombreCursoEli = "";
var imagenCursoEli = "";

/*=============================================
EDITAR CURSO
=============================================*/

$(".tablaCursos tbody").on("click", "button.btnEditarCurso", function(){


	var idCurso = $(this).attr("idcurso");
    // console.log("idCurso", idCurso);
	
	var datos = new FormData();
	datos.append("idCurso", idCurso);

	$.ajax({
		url: "ajax/cursos.ajax.php",
		method: "POST",
      	data: datos,
      	cache: false,
     	contentType: false,
     	processData: false,
     	dataType:"json",
     	success: function(respuesta){
            // console.log("respuesta", respuesta);

            idCursoEli = respuesta["id"];
            console.log("idCursoEli", idCursoEli);

            nombreCursoEli = respuesta["curso"];
            console.log("nombreCursoEli", nombreCursoEli);
            imagenCursoEli = respuesta["portada"];
            console.log("imagenCursoEli", imagenCursoEli);

     		$("#editarCurso").val(respuesta["curso"]);
     		$("#editarDescripcionCurso").val(respuesta["descripcion"]);

     		$("#editarCategoria").val(respuesta["categoria"]);
     		$("#editarPrecio").val(respuesta["precio"]);
            $("#editarRequisitoCurso").val(respuesta["requisitos"]);
     		$("#editarModalidad").val(respuesta["modalidad"]);
     		$("#editarObjetivoCurso").val(respuesta["objetivo"]);
            $("#fotoActual").val(respuesta["portada"]);
            $("#idCurso").val(respuesta["id"]);
            
            if(respuesta["portada"] != ""){

                $(".previsualizar").attr("src", respuesta["portada"]);
            }
     	}
	})
})

/*=============================================
REVISAR SI EL CURSO YA ESTÁ REGISTRADO
=============================================*/

$("#nuevoCurso").change(function(){

    $(".alert").remove();

    var curso = $(this).val();
    console.log("curso", curso);

    var datos = new FormData();
    datos.append("validarCurso", curso);

     $.ajax({
        url: "ajax/cursos.ajax.php",
        method:"POST",
        data: datos,
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success:function(respuesta){
            
            if(respuesta){

                $("#nuevoCurso").after('<div class="alert alert-warning">Este curso ya existe en la base de datos</div>');

                $("#nuevoCurso").val("");

            }

        }

    })
})

/*=============================================
IR A MODULO TEMAS
=============================================*/
$(".tablas").on("click", ".btnTemas", function(){

     var idCurso = $(this).attr("idCurso");

     swal({
        title: '¿Quieres ir a modulo de temas?',
        text: "¡Si no, cancelar la acción!",
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, ir a temas!'
     }).then(function(result){

        if(result.value){

            window.location = "modulos.php?idCurso="+idCurso;

        }
     })
})

/*=============================================
ELIMINAR CURSO
=============================================*/
$(".btnEliminar").on("click", function(){

    // console.log("idCursoEli", idCursoEli);
    // console.log("nombreCursoEli", nombreCursoEli);
    // console.log("imagenCursoEli", imagenCursoEli);

    swal({
        title: '¿Está seguro de borrar el curso?',
        text: "¡Si no lo está puede cancelar la acción!",
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, borrar curso!'
     }).then(function(result){

        if(result.value){

            window.location = "consultar-cursos.php?idCursoEli="+idCursoEli+"&nombreCursoEli="+nombreCursoEli+"&imagenCursoEli="+imagenCursoEli;
        }
     })


})
