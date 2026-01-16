jQuery(function ($) {
  $('#bp-topic-filter').on('input', function () {
    var query = $(this).val().toLowerCase();
    $('#bp-topic-select option').each(function () {
      var title = $(this).data('title');
      var category = $(this).data('category');
      var match = title.indexOf(query) !== -1 || category.indexOf(query) !== -1;
      $(this).toggle(match);
    });
  });

  $('#bp-planning-form').on('submit', function (e) {
    e.preventDefault();
    var data = $(this).serializeArray();
    data.push({ name: 'action', value: 'bp_generate_plan' });
    data.push({ name: 'nonce', value: $('#bp-generate-plan').data('nonce') });

    $.post(bpAdmin.ajaxUrl, data).done(function (response) {
      if (!response.success) {
        alert(response.data.message);
        return;
      }
      var list = $('#bp-plan-results');
      var container = list.find('.bp-plan-list');
      container.empty();
      response.data.suggestions.forEach(function (item) {
        var card = $('<div class="bp-plan-item"></div>');
        card.append('<h3>' + item.title + '</h3>');
        card.append('<p>' + item.description + '</p>');
        var button = $('<button class="button">Dodaj na spisak</button>');
        button.on('click', function () {
          $.post(bpAdmin.ajaxUrl, {
            action: 'bp_add_topic',
            nonce: bpAdmin.nonce,
            title: item.title,
            description: item.description,
            keywords: $('input[name="keywords"]').val(),
            category_id: $('select[name="category_id"]').val(),
            word_count: $('input[name="word_count"]').val(),
            assigned_user: $('select[name="assigned_user"]').val(),
          }).done(function (addResponse) {
            alert(addResponse.data.message);
          });
        });
        card.append(button);
        container.append(card);
      });
      list.show();
    });
  });

  $('#bp-write-topic').on('click', function () {
    var topicId = $('#bp-topic-select').val();
    var status = $('#bp-writing-status');
    status.text('Pisanje u toku...');
    $.post(bpAdmin.ajaxUrl, {
      action: 'bp_write_topic',
      nonce: $(this).data('nonce'),
      topic_id: topicId,
    }).done(function (response) {
      if (!response.success) {
        status.text(response.data.message);
        return;
      }
      status.text(response.data.message);
    });
  });
});
