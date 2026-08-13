import { TestBed } from '@angular/core/testing';

import { WorkflowApi } from './workflow-api';

describe('WorkflowApi', () => {
  let service: WorkflowApi;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(WorkflowApi);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
