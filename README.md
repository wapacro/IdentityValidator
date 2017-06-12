# Identity Validator
Identity Validator is a package that allows you to verify a user by his identity card or passport. The package supports XML templates to easily handle the local differences between the format of the machine readable lines of different identity cards or passports. I've planned some cool stuff other than just verifying, so stay tuned!

# Install
Since this is a Composer package, simply run the following command to install it:
```
composer require wapacro/identity-validator
```

# Use
Let your user enter the machine readable line on his identity document and let this package do the magic!
First, initialize the package;
```
$id = new \wapacro\IdentityValidator\IdentityValidator();
```

You can get a list of supported identity documents (and what country they belong to) by executing:
```
(array) $id->getSupportedTypes();
```

Then select the template you want to use. Select it by using the dot notation, which is shown as _notation_ in the supported documents list. You can also pass the desired template in the constructor.
```
$id->setTemplate('CH.id');
```

So let's get to it; Add the machine readable lines, which your user entered:
```
$id->addMachineReadableLines('IDCHEE2556414<5<<<<<<<<<<<<<<<\n0006013M2102182CHE<<<<<<<<<<<8\nEXAMPLE<<JOE<<<<<<<<<<');
```

... and finally validate those lines:
```
(bool) $id->validateMachineReadableLines();
```
