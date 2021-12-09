# sailfish3_contacts_export_php

A php script that opens the contacts sqlite database from SailfishOS v3, brings all contacts with their details and then checks if there are in a second contacts sqlite database from SailfishOS v4. If the contact does not exist, it creates a VCARD 2.1 entry and exports all the contacts in .vcf format.

