[module user admin forms]
[set resource-id="[get resource default=undefined overwrite]"]
[access feature=manage-[resource-id],get_[resource-id],(any)_[resource-id]]
[post]
[modal]