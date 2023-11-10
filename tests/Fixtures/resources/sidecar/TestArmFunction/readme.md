# Get started

cd resources/sidecar/MarkedToTiptap
yarn install

# Create types
uncomment these links in tsconfig.json 

// "declaration": true,
// "declarationMap": true,
// "emitDeclarationOnly": true,

and

yarn run types

comment these lines again

# Build

```
yarn build
```

# Test

```
yarn test
```

yarn test --grep 'formatted & combined tables'

# Deploy & activate

ensure right file in sidecar.php

first `cd` back to root

```bash
php artisan sidecar:deploy --activate --env=local
php artisan sidecar:deploy --activate --env=staging
php artisan sidecar:deploy --activate --env=preproduction
php artisan sidecar:deploy --activate --env=production
```
