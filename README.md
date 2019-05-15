# Codeigniter-Table_builder
Simple library for generating Boostrap 4 paginated tables in Codeigniter using base model and pagination classes from @yidas

### Requires
1. https://github.com/yidas/codeigniter-model
2. https://github.com/yidas/php-pagination

## Usage 

1. Composer install "yidas/codeigniter-model": "^2.18" and "yidas/pagination": "^1.0" and follow setup instructions from their repos
2. Upload the Table_builder.php library to application/libraries
3. Either load in application/config/autoload.php or from a controller
4. Ready to use

Example:

~~~
$data = $this->user_model->find();

$this->table_builder->setData($data);

$this->table_builder->setHeading('id', array(
  'label' => 'ID',
  'width' => '100',
));

$this->table_builder->setHeading('email', array(
  'label' => 'Email Address',
  'callback' => function($value) {
    return mailto($value);
  }
));

echo $this->table_builder->generate(); // OR SEND TO VIEW

~~~
