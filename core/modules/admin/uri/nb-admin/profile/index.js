Alpine.data("form_account", (user_id) => ({
    user_id: user_id,
    form_data: {
        
    },
    submit(e) {
      
      nb.api
        .put(nb.base_url + "/api/v1/users/" + user_id, {
          ...this.form_data,
          ...nb.edit.get_field_values(e.target),
        })
        .then((data) => {
          if (data.success) {
            nb.system_message(nb.text.profile_updated).then((data) => {
              history.back();
            });
          } else {
            nb.notify(data.message);
          }
        });
    },
    ...nb.forms,
  }));