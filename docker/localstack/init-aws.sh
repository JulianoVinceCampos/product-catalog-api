#!/bin/bash
echo "Creating S3 bucket for products..."
awslocal s3 mb s3://products-bucket --region us-east-1
awslocal s3api put-bucket-acl --bucket products-bucket --acl public-read
echo "S3 bucket 'products-bucket' created successfully."

echo "Creating SQS queue for product events..."
awslocal sqs create-queue --queue-name product-events --region us-east-1
echo "SQS queue 'product-events' created successfully."
