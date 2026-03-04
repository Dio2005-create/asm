$(document).ready(function () {
    // Initialisation de DataTable
    $('#example1').DataTable({
        "pageLength": 200
    });

    // Gestionnaire de raccourci clavier Alt + Y
    $(document).keydown(function (event) {
        if (event.altKey && event.key === "y") {
            event.preventDefault();
            $("#exampleModalLong").modal("show");
        }
    });

    // Gestionnaire pour cocher/décocher toutes les cases
    $('.select-all-checkbox').change(function () {
        var isChecked = $(this).prop('checked');
        $('.checkbox').prop('checked', isChecked);
        togglePrintButtonVisibility();
    });

    // Gestionnaire de visibilité du bouton d'impression
    $('.checkbox').change(function () {
        togglePrintButtonVisibility();
    });

    function togglePrintButtonVisibility() {
        var anyChecked = $('.checkbox:checked').length > 0;
        $('#printButton').toggle(anyChecked);
    }

    // Gestionnaire pour supprimer des données
    $('.data-remove').on('click', function (e) {
        e.preventDefault();
        var $delete = confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');
        if ($delete) {
            $(".loadBody").css("display", "block");
            var id = $(this).data('id');
            $.ajax({
                url: "{{ path('delere_releve') }}",
                type: 'post',
                data: {
                    id: id
                },
                dataType: "json",
                success: function (data) {
                    $(".loadBody").css("display", "none");
                    location.reload();
                },
                error: function (e) {
                    $(".loadBody").css("display", "none");
                    $(".chargementError").css("display", "block");
                }
            }).done(function () {
                $(".loadBody").css("display", "none");
            });
        } else {
            return false;
        }
    });

    // Gestionnaire pour éditer des données
    $(".edit").on('click', function (e) {
        $(".loadBody").css("display", "block");
        e.preventDefault();
        let id = $(this).data('id');
        $.ajax({
            url: "{{ path('add_form_releve') }}",
            type: 'post',
            data: {
                id: id,
            },
            dataType: 'json',
            success: function (data) {
                $('.create_body').html(data.form_html);
                $("#modal_create_body").modal('show');
                $(".loadBody").css("display", "none");
            },
            error: function (e) {
                $(".loadBody").css("display", "none");
                $(".chargementError").css("display", "block");
            }
        }).done(function () {
            $(".loadBody").css("display", "none");
        });
    });

    // Soumettre automatiquement le formulaire lorsqu'une sélection est faite
    document.getElementById('quartier').addEventListener('change', function () {
        document.getElementById('year-form').submit();
    });

    // Pré-remplir la date actuelle
    var date = new Date();
    var dateString = date.toISOString().split('T')[0];
    var dateInput = document.getElementById('dateReleve');
    dateInput.value = dateString;
    var monthInput = document.getElementById('months');
    var formattedMonth = date.toISOString().slice(0, 7);
    monthInput.value = formattedMonth;

    // Gestionnaire de sélection du client
    $('#releve_client').on('change', function () {
        var selectedValue = $(this).val();
        $.ajax({
            url: '{{ path("releve_check") }}',
            method: 'POST',
            data: {
                id: selectedValue
            },
            success: function (response) {
                var nom = document.getElementById('nom');
                nom.value = response.nom;
                var ancienReleveInput = document.getElementById('ancienReleve');
                ancienReleveInput.value = response.date;
                var moisInput = document.getElementById('mois');
                moisInput.value = response.mois;
                var ancienIndexInput = document.getElementById('ancienIndex');
                ancienIndexInput.value = response.ancienIndex;
                var nouvelIndexInput = document.getElementById('nouvelIndex');
                nouvelIndexInput.value = response.nouvelIndex;
                var anciensIndexInput = document.getElementById('anciensIndex');
                anciensIndexInput.value = response.nouvelIndex;
                var consommationInput = document.getElementById('consommation');
                consommationInput.value = response.consommation;
            },
            error: function (error) {
                // Gérer les erreurs ici
            }
        });
    });

    // Calculer la consommation et autres champs liés à l'index
    $('#nouvelleIndex').on('change', function () {
        var nouvelleIndexInput = document.getElementById('nouvelleIndex').value;
        var anciensIndexInput = document.getElementById('anciensIndex').value;
        var reponse = nouvelleIndexInput - anciensIndexInput;
        var consommationsInput = document.getElementById('consommations');
        consommationsInput.value = reponse;
        var limite = document.getElementById('limite').value;
        var quantite1Input = document.getElementById('quantite1');
        var quantite2Input = document.getElementById('quantite2');
        var montant1Input = document.getElementById('montant1');
        var montant2Input = document.getElementById('montant2');
        var puInput = document.getElementById('pu').value;
        var pusInput = document.getElementById('pus').value;
        if (reponse > limite) {
            var quantite2 = reponse - limite;
            quantite1Input.value = limite;
            quantite2Input.value = quantite2;
            montant1Input.value = limite * puInput;
            montant2Input.value = quantite2 * pusInput;
        } else {
            quantite1Input.value = reponse;
            montant1Input.value = reponse * puInput;
        }
    });

    // Initialiser les éléments de sélection Chosen
    $('.chosen-select').chosen({
        width: "100%"
    }).trigger('chosen:updated');

    // Soumettre le formulaire via AJAX
    $('#releveForm').on('submit', function (event) {
        event.preventDefault();

        var formData = $(this).serialize();
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            success: function (response) {
                $('#exampleModalLong').modal('hide');
                $('#nouvelleIndex').val('');
                $('#exampleModalLong input[type="text"]').val('');
                $('#exampleModalLong select').val('').trigger('chosen:updated');
            },
            error: function (xhr, status, error) {
                // Gérer les erreurs de réponse
                alert('Une erreur s\'est produite lors de la soumission du formulaire.');
            }
        });
    });
});
